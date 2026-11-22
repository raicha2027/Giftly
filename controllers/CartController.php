<?php

require_once __DIR__ . '/../config/db.php';

class CartController
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    private function validateCsrf($token)
    {
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function getCartOwner()
    {
        if (!empty($_SESSION['customer_id'])) {
            return [
                'user_id' => (int) $_SESSION['customer_id'],
                'session_id' => null
            ];
        }

        return [
            'user_id' => null,
            'session_id' => session_id()
        ];
    }

    private function getActiveCartId($create = true)
    {
        $owner = $this->getCartOwner();

        if ($owner['user_id'] !== null) {
            $stmt = $this->pdo->prepare("
                SELECT cart_id
                FROM giftly_carts
                WHERE user_id = ?
                  AND status = 'active'
                ORDER BY cart_id DESC
                LIMIT 1
            ");

            $stmt->execute([$owner['user_id']]);

        } else {
            $stmt = $this->pdo->prepare("
                SELECT cart_id
                FROM giftly_carts
                WHERE session_id = ?
                  AND status = 'active'
                ORDER BY cart_id DESC
                LIMIT 1
            ");

            $stmt->execute([$owner['session_id']]);
        }

        $cartId = $stmt->fetchColumn();

        if ($cartId) {
            return (int) $cartId;
        }

        if (!$create) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO giftly_carts
                (user_id, session_id, status)
            VALUES (?, ?, 'active')
        ");

        $stmt->execute([
            $owner['user_id'],
            $owner['session_id']
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getCartDetails()
    {
        $cartId = $this->getActiveCartId(false);

        if (!$cartId) {
            return [
                'items' => [],
                'summary' => [
                    'total_items' => 0,
                    'subtotal' => 0
                ]
            ];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                ci.cart_item_id,
                ci.product_id,
                ci.quantity,
                ci.packaging_id,

                p.product_name,
                p.price,
                p.stock_quantity,
                p.image_path,

                gp.packaging_name,
                COALESCE(gp.packaging_price, 0) AS packaging_price,

                (
                    (p.price + COALESCE(gp.packaging_price, 0))
                    * ci.quantity
                ) AS line_total

            FROM giftly_cart_items ci

            INNER JOIN giftly_products p
                ON ci.product_id = p.product_id

            LEFT JOIN giftly_gift_packaging gp
                ON ci.packaging_id = gp.packaging_id

            WHERE ci.cart_id = ?
              AND p.is_active = 1

            ORDER BY ci.added_at DESC
        ");

        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalItems = 0;
        $subtotal = 0;

        foreach ($items as $item) {
            $totalItems += (int) $item['quantity'];
            $subtotal += (float) $item['line_total'];
        }

        return [
            'items' => $items,
            'summary' => [
                'total_items' => $totalItems,
                'subtotal' => $subtotal
            ]
        ];
    }

    public function addToCart($productId, $quantity, $csrfToken)
    {
        if (!$this->validateCsrf($csrfToken)) {
            return 'Invalid request. Please refresh the page and try again.';
        }

        $productId = filter_var($productId, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if (!$productId || $productId < 1 || !$quantity || $quantity < 1) {
            return 'Invalid product or quantity.';
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                SELECT
                    product_id,
                    product_name,
                    price,
                    stock_quantity
                FROM giftly_products
                WHERE product_id = ?
                  AND is_active = 1
                FOR UPDATE
            ");

            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $this->pdo->rollBack();
                return 'This product is no longer available.';
            }

            $cartId = $this->getActiveCartId(true);

            $stmt = $this->pdo->prepare("
                SELECT cart_item_id, quantity
                FROM giftly_cart_items
                WHERE cart_id = ?
                  AND product_id = ?
                LIMIT 1
            ");

            $stmt->execute([$cartId, $productId]);
            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

            $newQuantity = $quantity;

            if ($existingItem) {
                $newQuantity += (int) $existingItem['quantity'];
            }

            if ($newQuantity > (int) $product['stock_quantity']) {
                $this->pdo->rollBack();
                return 'There is not enough stock available for this quantity.';
            }

            if ($existingItem) {
                $stmt = $this->pdo->prepare("
                    UPDATE giftly_cart_items
                    SET quantity = ?
                    WHERE cart_item_id = ?
                ");

                $stmt->execute([
                    $newQuantity,
                    $existingItem['cart_item_id']
                ]);

            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO giftly_cart_items
                        (cart_id, product_id, quantity)
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $cartId,
                    $productId,
                    $quantity
                ]);
            }

            $this->pdo->commit();

            return true;

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('Add to cart error: ' . $e->getMessage());

            return 'Unable to add this product to your cart.';
        }
    }

    public function updateCartItem($cartItemId, $quantity, $csrfToken)
    {
        if (!$this->validateCsrf($csrfToken)) {
            return 'Invalid request. Please refresh the page and try again.';
        }

        $cartItemId = filter_var($cartItemId, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if (!$cartItemId || $cartItemId < 1 || !$quantity || $quantity < 1) {
            return 'Please enter a valid quantity.';
        }

        try {
            $cartId = $this->getActiveCartId(false);

            if (!$cartId) {
                return 'Your cart is empty.';
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    ci.cart_item_id,
                    p.stock_quantity
                FROM giftly_cart_items ci

                INNER JOIN giftly_products p
                    ON ci.product_id = p.product_id

                WHERE ci.cart_item_id = ?
                  AND ci.cart_id = ?
                  AND p.is_active = 1

                LIMIT 1
            ");

            $stmt->execute([$cartItemId, $cartId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return 'Cart item not found.';
            }

            if ($quantity > (int) $item['stock_quantity']) {
                return 'The requested quantity is greater than the available stock.';
            }

            $stmt = $this->pdo->prepare("
                UPDATE giftly_cart_items
                SET quantity = ?
                WHERE cart_item_id = ?
                  AND cart_id = ?
            ");

            $stmt->execute([
                $quantity,
                $cartItemId,
                $cartId
            ]);

            return true;

        } catch (PDOException $e) {
            error_log('Update cart error: ' . $e->getMessage());

            return 'Unable to update your cart.';
        }
    }

    public function setPackaging($cartItemId, $packagingId, $csrfToken)
    {
        if (!$this->validateCsrf($csrfToken)) {
            return 'Invalid request. Please refresh the page and try again.';
        }

        $cartItemId = filter_var($cartItemId, FILTER_VALIDATE_INT);

        if (!$cartItemId || $cartItemId < 1) {
            return 'Invalid cart item.';
        }

        if (
            $packagingId === ''
            || $packagingId === null
            || $packagingId === '0'
            || $packagingId === 0
        ) {
            $packagingId = null;

        } else {
            $packagingId = filter_var($packagingId, FILTER_VALIDATE_INT);

            if (!$packagingId || $packagingId < 1) {
                return 'Please select a valid packaging option.';
            }
        }

        try {
            $cartId = $this->getActiveCartId(false);

            if (!$cartId) {
                return 'Your cart is empty.';
            }

            $stmt = $this->pdo->prepare("
                SELECT cart_item_id
                FROM giftly_cart_items
                WHERE cart_item_id = ?
                  AND cart_id = ?
                LIMIT 1
            ");

            $stmt->execute([$cartItemId, $cartId]);

            if (!$stmt->fetchColumn()) {
                return 'Cart item not found.';
            }

            if ($packagingId !== null) {
                $stmt = $this->pdo->prepare("
                    SELECT packaging_id
                    FROM giftly_gift_packaging
                    WHERE packaging_id = ?
                      AND is_active = 1
                    LIMIT 1
                ");

                $stmt->execute([$packagingId]);

                if (!$stmt->fetchColumn()) {
                    return 'This packaging option is no longer available.';
                }
            }

            $stmt = $this->pdo->prepare("
                UPDATE giftly_cart_items
                SET packaging_id = ?
                WHERE cart_item_id = ?
                  AND cart_id = ?
            ");

            $stmt->execute([
                $packagingId,
                $cartItemId,
                $cartId
            ]);

            return true;

        } catch (PDOException $e) {
            error_log('Set packaging error: ' . $e->getMessage());

            return 'Unable to update the gift packaging.';
        }
    }

    public function removeCartItem($cartItemId, $csrfToken)
    {
        if (!$this->validateCsrf($csrfToken)) {
            return 'Invalid request. Please refresh the page and try again.';
        }

        $cartItemId = filter_var($cartItemId, FILTER_VALIDATE_INT);

        if (!$cartItemId || $cartItemId < 1) {
            return 'Invalid cart item.';
        }

        try {
            $cartId = $this->getActiveCartId(false);

            if (!$cartId) {
                return 'Your cart is empty.';
            }

            $stmt = $this->pdo->prepare("
                DELETE FROM giftly_cart_items
                WHERE cart_item_id = ?
                  AND cart_id = ?
            ");

            $stmt->execute([
                $cartItemId,
                $cartId
            ]);

            if ($stmt->rowCount() === 0) {
                return 'Cart item not found.';
            }

            return true;

        } catch (PDOException $e) {
            error_log('Remove cart error: ' . $e->getMessage());

            return 'Unable to remove this item from your cart.';
        }
    }
}

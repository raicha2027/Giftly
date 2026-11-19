<?php

require_once __DIR__ . '/../config/db.php';

class CheckoutController
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

    private function getActiveCartId()
    {
        if (!empty($_SESSION['customer_id'])) {
            $stmt = $this->pdo->prepare("
                SELECT cart_id
                FROM carts
                WHERE user_id = ?
                  AND status = 'active'
                ORDER BY cart_id DESC
                LIMIT 1
            ");

            $stmt->execute([(int) $_SESSION['customer_id']]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT cart_id
                FROM carts
                WHERE session_id = ?
                  AND status = 'active'
                ORDER BY cart_id DESC
                LIMIT 1
            ");

            $stmt->execute([session_id()]);
        }

        $cartId = $stmt->fetchColumn();

        return $cartId ? (int) $cartId : null;
    }

    private function getSelectedIds()
    {
        $ids = $_SESSION['selected_cart_item_ids'] ?? [];

        if (!is_array($ids)) {
            return [];
        }

        $validIds = [];

        foreach ($ids as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT);

            if ($id && $id > 0) {
                $validIds[] = (int) $id;
            }
        }

        return array_values(array_unique($validIds));
    }

    /*
    |--------------------------------------------------------------------------
    | GET SELECTED GIFTS INCLUDING PACKAGING
    |--------------------------------------------------------------------------
    */

    public function getSelectedCartDetails()
    {
        $selectedIds = $this->getSelectedIds();
        $cartId = $this->getActiveCartId();

        if (!$cartId || empty($selectedIds)) {
            return [
                'items' => [],
                'summary' => [
                    'total_items' => 0,
                    'subtotal' => 0
                ]
            ];
        }

        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

        $sql = "
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

            FROM cart_items ci

            INNER JOIN products p
                ON ci.product_id = p.product_id

            LEFT JOIN gift_packaging gp
                ON ci.packaging_id = gp.packaging_id

            WHERE ci.cart_id = ?
              AND ci.cart_item_id IN ($placeholders)
              AND p.is_active = 1

            ORDER BY ci.added_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute(array_merge([$cartId], $selectedIds));

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

    /*
    |--------------------------------------------------------------------------
    | PLACE ORDER
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | GET CUSTOMER ORDER HISTORY
    |--------------------------------------------------------------------------
    */

    public function getOrderHistory()
    {
        if (empty($_SESSION['customer_id'])) {
            return [];
        }

        try {
            $customerId = (int) $_SESSION['customer_id'];

            $stmt = $this->pdo->prepare("
                SELECT
                    order_id,
                    order_number,
                    placed_at,
                    recipient_name,
                    delivery_method,
                    total_amount,
                    order_status
                FROM orders
                WHERE user_id = ?
                ORDER BY placed_at DESC
            ");

            $stmt->execute([$customerId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log('Order history error: ' . $e->getMessage());

            return 'Unable to retrieve your order history. Please try again later.';
        }
    }

    public function placeOrder($data, $csrfToken)
    {
        if (!$this->validateCsrf($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Your session has expired. Please refresh the page and try again.'
            ];
        }

        if (empty($_SESSION['customer_id'])) {
            return [
                'success' => false,
                'message' => 'Please log in before placing your order.'
            ];
        }

        $customerId = (int) $_SESSION['customer_id'];
        $cartId = $this->getActiveCartId();
        $selectedIds = $this->getSelectedIds();

        if (!$cartId || empty($selectedIds)) {
            return [
                'success' => false,
                'message' => 'No gifts have been selected. Please return to your cart.'
            ];
        }

        // Customer details
        $recipientName = trim($data['recipient_name'] ?? '');
        $contactPhone = trim($data['contact_phone'] ?? '');
        $deliveryMethod = $data['delivery_method'] ?? 'delivery';
        $deliveryAddress = trim($data['delivery_address'] ?? '');
        $city = trim($data['city'] ?? '');
        $deliveryNotes = trim($data['delivery_notes'] ?? '');
        $giftMessage = trim($data['gift_message'] ?? '');
        $paymentMethod = $data['payment_method'] ?? '';

        if ($recipientName === '' || $contactPhone === '') {
            return [
                'success' => false,
                'message' => 'Please provide the recipient name and contact phone number.'
            ];
        }

        if (strlen($recipientName) > 120 || strlen($contactPhone) > 20) {
            return [
                'success' => false,
                'message' => 'Some of your details are too long. Please check and try again.'
            ];
        }

        if (!in_array($deliveryMethod, ['delivery', 'pickup'], true)) {
            return [
                'success' => false,
                'message' => 'Please select a valid delivery method.'
            ];
        }

        if ($deliveryMethod === 'delivery' && $deliveryAddress === '') {
            return [
                'success' => false,
                'message' => 'Please enter your delivery address.'
            ];
        }

        if (
            !in_array($paymentMethod, [
                'mobile_money',
                'card',
                'cash_on_delivery'
            ], true)
        ) {
            return [
                'success' => false,
                'message' => 'Please select a valid payment method.'
            ];
        }

        if (
            strlen($deliveryAddress) > 255 ||
            strlen($city) > 80 ||
            strlen($deliveryNotes) > 255 ||
            strlen($giftMessage) > 255
        ) {
            return [
                'success' => false,
                'message' => 'One or more of your order details are too long.'
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

            /*
             * Retrieve selected cart items and their packaging.
             * Lock the cart rows and products during order creation.
             */

            $sql = "
                SELECT
                    ci.cart_item_id,
                    ci.product_id,
                    ci.quantity,
                    ci.packaging_id,

                    p.product_name,
                    p.price,
                    p.stock_quantity,

                    gp.packaging_name,
                    COALESCE(gp.packaging_price, 0) AS packaging_price

                FROM cart_items ci

                INNER JOIN products p
                    ON ci.product_id = p.product_id

                LEFT JOIN gift_packaging gp
                    ON ci.packaging_id = gp.packaging_id

                WHERE ci.cart_id = ?
                  AND ci.cart_item_id IN ($placeholders)
                  AND p.is_active = 1

                FOR UPDATE
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute(array_merge([$cartId], $selectedIds));

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($items) !== count($selectedIds)) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => 'Some selected gifts are no longer available in your cart. Please return to your cart and select your gifts again.'
                ];
            }

            $subtotal = 0;

            foreach ($items as $item) {
                if ((int) $item['stock_quantity'] < (int) $item['quantity']) {
                    $this->pdo->rollBack();

                    return [
                        'success' => false,
                        'message' => 'A selected gift does not have enough stock. Please return to your cart.'
                    ];
                }

                if ($item['price'] === null || (float) $item['price'] <= 0) {
                    $this->pdo->rollBack();

                    return [
                        'success' => false,
                        'message' => 'A selected gift does not have a valid price. Please return to your cart.'
                    ];
                }

                $productCost = (float) $item['price'];
                $packagingCost = (float) $item['packaging_price'];
                $quantity = (int) $item['quantity'];

                $subtotal += ($productCost + $packagingCost) * $quantity;
            }

            // Delivery fee is currently zero.
            $deliveryFee = 0;
            $totalAmount = $subtotal + $deliveryFee;

            $orderNumber = 'GFT'
                . date('ymd')
                . strtoupper(bin2hex(random_bytes(3)));

            /*
            |--------------------------------------------------------------------------
            | INSERT ORDER
            |--------------------------------------------------------------------------
            */

            $stmt = $this->pdo->prepare("
                INSERT INTO orders (
                    order_number,
                    user_id,
                    recipient_name,
                    contact_phone,
                    delivery_method,
                    delivery_address,
                    city,
                    delivery_notes,
                    gift_message,
                    subtotal,
                    delivery_fee,
                    total_amount,
                    order_status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
                )
            ");

            $stmt->execute([
                $orderNumber,
                $customerId,
                $recipientName,
                $contactPhone,
                $deliveryMethod,
                $deliveryMethod === 'delivery' ? $deliveryAddress : null,
                $deliveryMethod === 'delivery' ? $city : null,
                $deliveryNotes !== '' ? $deliveryNotes : null,
                $giftMessage !== '' ? $giftMessage : null,
                $subtotal,
                $deliveryFee,
                $totalAmount
            ]);

            $orderId = (int) $this->pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | INSERT ORDER ITEMS INCLUDING PACKAGING
            |--------------------------------------------------------------------------
            */

            $itemStmt = $this->pdo->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    unit_price,
                    packaging_name,
                    packaging_price,
                    quantity
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['price'],
                    $item['packaging_name'],
                    $item['packaging_price'],
                    $item['quantity']
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE PENDING PAYMENT RECORD
            |--------------------------------------------------------------------------
            */

            $paymentStmt = $this->pdo->prepare("
                INSERT INTO payments (
                    order_id,
                    payment_method,
                    amount,
                    currency,
                    payment_status
                ) VALUES (?, ?, ?, 'GHS', 'pending')
            ");

            $paymentStmt->execute([
                $orderId,
                $paymentMethod,
                $totalAmount
            ]);

            /*
            |--------------------------------------------------------------------------
            | REMOVE ONLY PURCHASED GIFTS FROM CART
            |--------------------------------------------------------------------------
            */

            $deleteSql = "
                DELETE FROM cart_items
                WHERE cart_id = ?
                  AND cart_item_id IN ($placeholders)
            ";

            $deleteStmt = $this->pdo->prepare($deleteSql);

            $deleteStmt->execute(array_merge([$cartId], $selectedIds));

            $remainingStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM cart_items
                WHERE cart_id = ?
            ");

            $remainingStmt->execute([$cartId]);

            $remainingItems = (int) $remainingStmt->fetchColumn();

            if ($remainingItems === 0) {
                $updateCart = $this->pdo->prepare("
                    UPDATE carts
                    SET status = 'converted'
                    WHERE cart_id = ?
                ");

                $updateCart->execute([$cartId]);
            }

            $this->pdo->commit();

            unset($_SESSION['selected_cart_item_ids']);

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'message' => 'Your order has been placed successfully.'
            ];

        } catch (PDOException $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('Checkout error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'We could not complete your order. Please try again.'
            ];

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('Checkout error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Something went wrong while placing your order.'
            ];
        }
    }
}

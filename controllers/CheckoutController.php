<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';

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
                FROM giftly_carts
                WHERE user_id = ?
                  AND status = 'active'
                ORDER BY cart_id DESC
                LIMIT 1
            ");

            $stmt->execute([(int) $_SESSION['customer_id']]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT cart_id
                FROM giftly_carts
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
    | GET SELECTED GIFTS
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

        $placeholders = implode(
            ',',
            array_fill(0, count($selectedIds), '?')
        );

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
            FROM giftly_cart_items ci
            INNER JOIN giftly_products p
                ON ci.product_id = p.product_id
            LEFT JOIN giftly_gift_packaging gp
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
    | CUSTOMER ORDER HISTORY
    |--------------------------------------------------------------------------
    */

    public function getOrderHistory()
    {
        if (empty($_SESSION['customer_id'])) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    order_id,
                    order_number,
                    placed_at,
                    recipient_name,
                    delivery_method,
                    total_amount,
                    order_status
                FROM giftly_orders
                WHERE user_id = ?
                ORDER BY placed_at DESC
            ");

            $stmt->execute([
                (int) $_SESSION['customer_id']
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log('Order history error: ' . $e->getMessage());

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PLACE ORDER
    |--------------------------------------------------------------------------
    */

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

            $placeholders = implode(
                ',',
                array_fill(0, count($selectedIds), '?')
            );

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
                FROM giftly_cart_items ci
                INNER JOIN giftly_products p
                    ON ci.product_id = p.product_id
                LEFT JOIN giftly_gift_packaging gp
                    ON ci.packaging_id = gp.packaging_id
                WHERE ci.cart_id = ?
                  AND ci.cart_item_id IN ($placeholders)
                  AND p.is_active = 1
                FOR UPDATE
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute(
                array_merge([$cartId], $selectedIds)
            );

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($items) !== count($selectedIds)) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => 'Some selected gifts are no longer available in your cart. Please return to your cart and select them again.'
                ];
            }

            $subtotal = 0;

            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];
                $stock = (int) $item['stock_quantity'];

                if ($quantity <= 0 || $stock < $quantity) {
                    $this->pdo->rollBack();

                    return [
                        'success' => false,
                        'message' => 'A selected gift does not have enough stock. Please return to your cart.'
                    ];
                }

                if (
                    $item['price'] === null ||
                    (float) $item['price'] <= 0
                ) {
                    $this->pdo->rollBack();

                    return [
                        'success' => false,
                        'message' => 'A selected gift does not have a valid price. Please return to your cart.'
                    ];
                }

                $subtotal += (
                    (float) $item['price'] +
                    (float) $item['packaging_price']
                ) * $quantity;
            }

            $deliveryFee = 0;
            $totalAmount = $subtotal + $deliveryFee;

            $orderNumber = 'GFT'
                . date('ymd')
                . strtoupper(bin2hex(random_bytes(3)));

            // Create the order.

            $stmt = $this->pdo->prepare("
                INSERT INTO giftly_orders (
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

            // Save the order items.

            $itemStmt = $this->pdo->prepare("
                INSERT INTO giftly_order_items (
                    order_id,
                    product_id,
                    product_name,
                    unit_price,
                    packaging_name,
                    packaging_price,
                    quantity
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            // Only Cash on Delivery deducts stock at this stage.

            $stockStmt = null;

            if ($paymentMethod === 'cash_on_delivery') {
                $stockStmt = $this->pdo->prepare("
                    UPDATE giftly_products
                    SET stock_quantity = stock_quantity - ?
                    WHERE product_id = ?
                      AND stock_quantity >= ?
                ");
            }

            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];

                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['price'],
                    $item['packaging_name'],
                    $item['packaging_price'],
                    $quantity
                ]);

                if ($paymentMethod === 'cash_on_delivery') {
                    $stockStmt->execute([
                        $quantity,
                        $item['product_id'],
                        $quantity
                    ]);

                    if ($stockStmt->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Product stock could not be updated.'
                        );
                    }
                }
            }

            // Create the pending payment record.

            $paymentStmt = $this->pdo->prepare("
                INSERT INTO giftly_payments (
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

            // Remove purchased gifts from the cart.

            $deleteSql = "
                DELETE FROM giftly_cart_items
                WHERE cart_id = ?
                  AND cart_item_id IN ($placeholders)
            ";

            $deleteStmt = $this->pdo->prepare($deleteSql);

            $deleteStmt->execute(
                array_merge([$cartId], $selectedIds)
            );

            // Check whether the cart is now empty.

            $remainingStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM giftly_cart_items
                WHERE cart_id = ?
            ");

            $remainingStmt->execute([$cartId]);

            $remainingItems = (int) $remainingStmt->fetchColumn();

            if ($remainingItems === 0) {
                $updateCart = $this->pdo->prepare("
                    UPDATE giftly_carts
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
                'total_amount' => $totalAmount,
                'message' => 'Your order has been placed successfully.'
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('Checkout error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'We could not complete your order. Please try again.'
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INITIALIZE PAYSTACK PAYMENT
    |--------------------------------------------------------------------------
    */

    public function initializePaystackPayment($orderId)
    {
        if (empty($_SESSION['customer_id'])) {
            return [
                'success' => false,
                'message' => 'Please log in before making payment.'
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'Payment service is unavailable. Please enable PHP cURL.'
            ];
        }

        $customerId = (int) $_SESSION['customer_id'];
        $orderId = (int) $orderId;

        if ($orderId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid order.'
            ];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    o.order_id,
                    o.order_number,
                    o.total_amount,
                    o.order_status,
                    p.payment_id,
                    p.payment_method,
                    p.amount,
                    p.currency,
                    p.payment_status
                FROM giftly_orders o
                INNER JOIN giftly_payments p
                    ON p.order_id = o.order_id
                WHERE o.order_id = ?
                  AND o.user_id = ?
                LIMIT 1
            ");

            $stmt->execute([$orderId, $customerId]);

            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'We could not find this order or its payment.'
                ];
            }

            if (
                !in_array($payment['payment_method'], [
                    'card',
                    'mobile_money'
                ], true)
            ) {
                return [
                    'success' => false,
                    'message' => 'This order does not use Paystack.'
                ];
            }

            if ($payment['payment_status'] === 'successful') {
                return [
                    'success' => false,
                    'message' => 'This order has already been paid.'
                ];
            }

            if ($payment['payment_status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'This payment is not available for processing.'
                ];
            }

            if ($payment['order_status'] === 'cancelled') {
                return [
                    'success' => false,
                    'message' => 'This order has been cancelled.'
                ];
            }

            $amountInPesewas = (int) round(
                (float) $payment['amount'] * 100
            );

            if (
                $amountInPesewas <= 0 ||
                abs(
                    (float) $payment['amount'] -
                    (float) $payment['total_amount']
                ) > 0.001 ||
                $payment['currency'] !== 'GHS'
            ) {
                return [
                    'success' => false,
                    'message' => 'The order amount or currency is invalid.'
                ];
            }

            $emailStmt = $this->pdo->prepare("
                SELECT email
                FROM giftly_users
                WHERE user_id = ?
                LIMIT 1
            ");

            $emailStmt->execute([$customerId]);

            $email = $emailStmt->fetchColumn();

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'A valid email address is required for payment.'
                ];
            }

            $callbackUrl = defined('PAYSTACK_CALLBACK_URL')
                ? PAYSTACK_CALLBACK_URL
                : 'http://169.239.251.102:442/~fatma.abdourahmane/Giftly/pages/checkout/verify_payment.php';

            $channels = $payment['payment_method'] === 'card'
                ? ['card']
                : ['mobile_money'];

            $payload = [
                'email' => $email,
                'amount' => $amountInPesewas,
                'currency' => 'GHS',
                'callback_url' => $callbackUrl,
                'channels' => $channels,
                'metadata' => [
                    'order_id' => (int) $payment['order_id'],
                    'order_number' => $payment['order_number']
                ]
            ];

            $ch = curl_init(
                'https://api.paystack.co/transaction/initialize'
            );

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($response === false) {
                error_log(
                    'Paystack initialization cURL error: ' . $curlError
                );

                return [
                    'success' => false,
                    'message' => 'Could not connect to Paystack. Please try again.'
                ];
            }

            $result = json_decode($response, true);

            if (
                $httpCode < 200 ||
                $httpCode >= 300 ||
                empty($result['status']) ||
                empty($result['data']['authorization_url']) ||
                empty($result['data']['reference'])
            ) {
                error_log('Paystack initialization failed: ' . $response);

                return [
                    'success' => false,
                    'message' => $result['message'] ??
                        'Paystack could not start your payment. Please try again.'
                ];
            }

            $reference = $result['data']['reference'];

            $update = $this->pdo->prepare("
                UPDATE giftly_payments
                SET gateway_reference = ?,
                    provider = 'Paystack'
                WHERE payment_id = ?
                  AND payment_status = 'pending'
            ");

            $update->execute([
                $reference,
                (int) $payment['payment_id']
            ]);

            if ($update->rowCount() !== 1) {
                return [
                    'success' => false,
                    'message' => 'Could not link the payment to your order. Please contact Giftly.'
                ];
            }

            return [
                'success' => true,
                'authorization_url' => $result['data']['authorization_url'],
                'reference' => $reference,
                'order_id' => (int) $payment['order_id']
            ];

        } catch (Throwable $e) {
            error_log(
                'Paystack initialization error: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Something went wrong while starting your payment.'
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY PAYSTACK PAYMENT AND DEDUCT STOCK
    |--------------------------------------------------------------------------
    */

    public function verifyPayment($reference)
    {
        if (empty($_SESSION['customer_id'])) {
            return 'Please log in to complete payment.';
        }

        if (
            !is_string($reference) ||
            $reference === '' ||
            strlen($reference) > 100 ||
            !preg_match('/^[A-Za-z0-9._=-]+$/', $reference)
        ) {
            return 'Invalid payment reference.';
        }

        if (!function_exists('curl_init')) {
            return 'Payment verification is unavailable. Please enable PHP cURL.';
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    p.payment_id,
                    p.order_id,
                    p.amount,
                    p.currency,
                    p.payment_status,
                    p.gateway_reference,
                    o.order_number,
                    o.total_amount
                FROM giftly_payments p
                INNER JOIN giftly_orders o
                    ON o.order_id = p.order_id
                WHERE p.gateway_reference = ?
                  AND o.user_id = ?
                  AND p.provider = 'Paystack'
                LIMIT 1
            ");

            $stmt->execute([
                $reference,
                (int) $_SESSION['customer_id']
            ]);

            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                return 'We could not find a payment linked to this account.';
            }

            // A successful payment has already had its stock deducted.

            if ($payment['payment_status'] === 'successful') {
                return [
                    'success' => true,
                    'order_id' => (int) $payment['order_id'],
                    'order_number' => $payment['order_number'],
                    'total_amount' => (float) $payment['amount']
                ];
            }

            if ($payment['payment_status'] !== 'pending') {
                return 'This payment is no longer pending.';
            }

            // Ask Paystack directly whether payment succeeded.

            $ch = curl_init(
                'https://api.paystack.co/transaction/verify/' .
                rawurlencode($reference)
            );

            curl_setopt_array($ch, [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($response === false) {
                error_log(
                    'Paystack verification cURL error: ' . $curlError
                );

                return 'Could not contact Paystack to verify your payment. Please try again.';
            }

            $result = json_decode($response, true);
            $data = $result['data'] ?? [];

            if (
                $httpCode < 200 ||
                $httpCode >= 300 ||
                empty($result['status']) ||
                ($data['status'] ?? '') !== 'success'
            ) {
                return 'Paystack has not confirmed this payment as successful.';
            }

            // Check the amount, currency and reference.

            $expectedAmount = (int) round(
                (float) $payment['amount'] * 100
            );

            $paidAmount = (int) ($data['amount'] ?? 0);
            $paidCurrency = $data['currency'] ?? '';
            $paidReference = $data['reference'] ?? '';

            if (
                $paidReference !== $reference ||
                $paidAmount !== $expectedAmount ||
                $paidCurrency !== $payment['currency'] ||
                $paidCurrency !== 'GHS' ||
                abs(
                    (float) $payment['amount'] -
                    (float) $payment['total_amount']
                ) > 0.001
            ) {
                error_log(
                    'Paystack verification mismatch for order ' .
                    $payment['order_number']
                );

                return 'The payment details did not match your order. Please contact Giftly.';
            }

            $metadataOrderId = $data['metadata']['order_id'] ?? null;

            if (
                $metadataOrderId !== null &&
                (int) $metadataOrderId !== (int) $payment['order_id']
            ) {
                return 'The payment does not match this order.';
            }

            /*
             * Begin one transaction for:
             * 1. Marking the payment successful.
             * 2. Deducting the purchased quantities.
             * 3. Confirming the order.
             *
             * If any stock update fails, everything is rolled back.
             */

            $this->pdo->beginTransaction();

            // Only one verification request can change pending to successful.

            $updatePayment = $this->pdo->prepare("
                UPDATE giftly_payments
                SET payment_status = 'successful',
                    paid_at = NOW()
                WHERE payment_id = ?
                  AND gateway_reference = ?
                  AND payment_status = 'pending'
            ");

            $updatePayment->execute([
                (int) $payment['payment_id'],
                $reference
            ]);

            if ($updatePayment->rowCount() !== 1) {
                $this->pdo->rollBack();

                // Check whether another request already completed it.

                $checkPayment = $this->pdo->prepare("
                    SELECT payment_status
                    FROM giftly_payments
                    WHERE payment_id = ?
                    LIMIT 1
                ");

                $checkPayment->execute([
                    (int) $payment['payment_id']
                ]);

                if ($checkPayment->fetchColumn() === 'successful') {
                    return [
                        'success' => true,
                        'order_id' => (int) $payment['order_id'],
                        'order_number' => $payment['order_number'],
                        'total_amount' => (float) $payment['amount']
                    ];
                }

                return 'The payment status changed while it was being verified. Please refresh your order.';
            }

            // Get the products and quantities saved for this order.

            $orderItemsStmt = $this->pdo->prepare("
                SELECT product_id, quantity
                FROM giftly_order_items
                WHERE order_id = ?
                FOR UPDATE
            ");

            $orderItemsStmt->execute([
                (int) $payment['order_id']
            ]);

            $orderItems = $orderItemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($orderItems)) {
                throw new RuntimeException(
                    'No order items were found for this payment.'
                );
            }

            // Deduct stock for every purchased product.

            $stockStmt = $this->pdo->prepare("
                UPDATE giftly_products
                SET stock_quantity = stock_quantity - ?
                WHERE product_id = ?
                  AND stock_quantity >= ?
            ");

            foreach ($orderItems as $item) {
                $quantity = (int) $item['quantity'];
                $productId = (int) $item['product_id'];

                if ($quantity <= 0 || $productId <= 0) {
                    throw new RuntimeException(
                        'Invalid product quantity in the order.'
                    );
                }

                $stockStmt->execute([
                    $quantity,
                    $productId,
                    $quantity
                ]);

                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Insufficient stock for product ID ' . $productId
                    );
                }
            }

            // Confirm the order after successful payment and stock deduction.

            $updateOrder = $this->pdo->prepare("
                UPDATE giftly_orders
                SET order_status = 'confirmed'
                WHERE order_id = ?
                  AND user_id = ?
                  AND order_status = 'pending'
            ");

            $updateOrder->execute([
                (int) $payment['order_id'],
                (int) $_SESSION['customer_id']
            ]);

            if ($updateOrder->rowCount() !== 1) {
                throw new RuntimeException(
                    'The order could not be confirmed.'
                );
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'order_id' => (int) $payment['order_id'],
                'order_number' => $payment['order_number'],
                'total_amount' => (float) $payment['amount']
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log(
                'Paystack verification error: ' . $e->getMessage()
            );

            return 'Something went wrong while verifying your payment. Please contact Giftly if money was deducted.';
        }
    }
}

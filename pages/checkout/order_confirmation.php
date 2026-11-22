<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/db.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount)
{
    return 'GH₵ ' . number_format((float) $amount, 2);
}

// Make sure the customer is logged in.
if (empty($_SESSION['customer_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$customerId = (int) $_SESSION['customer_id'];

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId <= 0) {
    http_response_code(404);
    exit('Order not found.');
}

// Create CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| HANDLE ORDER CANCELLATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $_SESSION['cancel_message'] = 'Security check failed. Please try again.';
        $_SESSION['cancel_message_type'] = 'error';

        header('Location: order_confirmation.php?order_id=' . $orderId);
        exit;
    }

    try {

        $pdo->beginTransaction();

        // Verify that this order belongs to the logged-in customer.
        $checkStmt = $pdo->prepare("
            SELECT
                o.order_status,
                (
                    SELECT p.payment_status
                    FROM giftly_payments p
                    WHERE p.order_id = o.order_id
                    ORDER BY p.payment_id DESC
                    LIMIT 1
                ) AS payment_status
            FROM giftly_orders o
            WHERE o.order_id = ?
              AND o.user_id = ?
            FOR UPDATE
        ");

        $checkStmt->execute([$orderId, $customerId]);
        $currentOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentOrder) {
            throw new Exception('Order not found.');
        }

        // Only pending orders can be cancelled.
        if ($currentOrder['order_status'] !== 'pending') {
            throw new Exception('This order can no longer be cancelled.');
        }

        // Do not allow cancellation after successful payment.
        if (($currentOrder['payment_status'] ?? 'pending') === 'successful') {
            throw new Exception('This order has already been paid.');
        }

        // Get the products and quantities from the order.
        $orderItemsStmt = $pdo->prepare("
            SELECT product_id, quantity
            FROM giftly_order_items
            WHERE order_id = ?
        ");

        $orderItemsStmt->execute([$orderId]);
        $itemsToRestore = $orderItemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($itemsToRestore)) {
            throw new Exception('No order items were found to restore.');
        }

        // Find the customer's active cart.
        $cartStmt = $pdo->prepare("
            SELECT cart_id
            FROM giftly_carts
            WHERE user_id = ?
              AND status = 'active'
            ORDER BY cart_id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $cartStmt->execute([$customerId]);
        $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);

        // Create a new active cart if one does not exist.
        if (!$cart) {

            $createCartStmt = $pdo->prepare("
                INSERT INTO giftly_carts (user_id, session_id, status)
                VALUES (?, NULL, 'active')
            ");

            $createCartStmt->execute([$customerId]);

            $cartId = (int) $pdo->lastInsertId();

        } else {

            $cartId = (int) $cart['cart_id'];
        }

        // Prepare statements to restore products.
        $findCartItemStmt = $pdo->prepare("
            SELECT cart_item_id, quantity
            FROM giftly_cart_items
            WHERE cart_id = ?
              AND product_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $updateCartItemStmt = $pdo->prepare("
            UPDATE giftly_cart_items
            SET quantity = quantity + ?
            WHERE cart_item_id = ?
        ");

        $insertCartItemStmt = $pdo->prepare("
            INSERT INTO giftly_cart_items (cart_id, product_id, quantity)
            VALUES (?, ?, ?)
        ");

        // Restore each product to the cart.
        foreach ($itemsToRestore as $item) {

            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $findCartItemStmt->execute([$cartId, $productId]);
            $existingItem = $findCartItemStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingItem) {

                $updateCartItemStmt->execute([
                    $quantity,
                    (int) $existingItem['cart_item_id']
                ]);

            } else {

                $insertCartItemStmt->execute([
                    $cartId,
                    $productId,
                    $quantity
                ]);
            }
        }

        // Mark the order as cancelled.
        $cancelStmt = $pdo->prepare("
            UPDATE giftly_orders
            SET order_status = 'cancelled'
            WHERE order_id = ?
              AND user_id = ?
              AND order_status = 'pending'
        ");

        $cancelStmt->execute([$orderId, $customerId]);

        if ($cancelStmt->rowCount() !== 1) {
            throw new Exception('Unable to cancel this order. Please try again.');
        }

        $pdo->commit();

        unset($_SESSION['selected_cart_item_ids']);

        header('Location: ../cart/cart.php?cancelled=1');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Order cancellation error: ' . $e->getMessage());

        $_SESSION['cancel_message'] = $e instanceof PDOException
            ? 'Something went wrong while cancelling your order. Please try again.'
            : $e->getMessage();

        $_SESSION['cancel_message_type'] = 'error';

        header('Location: order_confirmation.php?order_id=' . $orderId);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| DISPLAY ERROR MESSAGES
|--------------------------------------------------------------------------
*/

$cancelMessage = $_SESSION['cancel_message'] ?? '';
$cancelMessageType = $_SESSION['cancel_message_type'] ?? '';
$checkoutError = $_SESSION['checkout_error'] ?? '';

unset(
    $_SESSION['cancel_message'],
    $_SESSION['cancel_message_type'],
    $_SESSION['checkout_error']
);

/*
|--------------------------------------------------------------------------
| GET ORDER DETAILS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            o.order_id,
            o.order_number,
            o.recipient_name,
            o.contact_phone,
            o.delivery_method,
            o.delivery_address,
            o.city,
            o.delivery_notes,
            o.gift_message,
            o.subtotal,
            o.delivery_fee,
            o.total_amount,
            o.order_status,
            o.placed_at,
            p.payment_method,
            p.payment_status
        FROM giftly_orders o
        LEFT JOIN giftly_payments p
            ON p.payment_id = (
                SELECT MAX(latest_payment.payment_id)
                FROM giftly_payments latest_payment
                WHERE latest_payment.order_id = o.order_id
            )
        WHERE o.order_id = ?
          AND o.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        exit('Order not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    $itemStmt = $pdo->prepare("
        SELECT
            product_name,
            unit_price,
            quantity,
            line_total
        FROM giftly_order_items
        WHERE order_id = ?
        ORDER BY order_item_id ASC
    ");

    $itemStmt->execute([$orderId]);
    $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Order confirmation error: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load your order. Please try again.');
}

if (empty($orderItems)) {
    http_response_code(404);
    exit('No items were found for this order.');
}

// Format statuses.
$orderStatus = ucfirst(
    str_replace('_', ' ', $order['order_status'])
);

$paymentStatus = ucfirst(
    str_replace('_', ' ', $order['payment_status'] ?? 'pending')
);

$paymentMethod = ucwords(
    str_replace('_', ' ', $order['payment_method'] ?? 'Not recorded')
);

$deliveryMethod = ucfirst(
    str_replace('_', ' ', $order['delivery_method'])
);

// Only allow cancellation for pending and unpaid orders.
$canCancel = (
    $order['order_status'] === 'pending'
    && ($order['payment_status'] ?? 'pending') !== 'successful'
);

// Allow payment retry only for pending card or Mobile Money orders.
$canRetryPayment = (
    $order['order_status'] === 'pending'
    && ($order['payment_status'] ?? 'pending') !== 'successful'
    && in_array(
        $order['payment_method'] ?? '',
        ['card', 'mobile_money'],
        true
    )
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Order Confirmation | Giftly</title>

    <link rel="stylesheet" href="/~fatma.abdourahmane/Giftly/assets/css/style.css?v=6">

    <style>
        .receipt-page {
            background: #faf7f5;
            min-height: 100vh;
            padding: 40px 18px;
        }

        .receipt-container {
            max-width: 850px;
            margin: 0 auto;
        }

        .receipt-header {
            background: #641b32;
            color: #fff;
            text-align: center;
            padding: 38px 20px;
            border-radius: 14px 14px 0 0;
        }

        .receipt-header h1 {
            font-size: 30px;
            margin: 0 0 10px;
            color: #fff;
        }

        .receipt-header p {
            color: #f5e9ed;
            margin: 0;
        }

        .receipt-content {
            background: #fff;
            padding: 32px;
            border: 1px solid #eee3e6;
            border-top: none;
            border-radius: 0 0 14px 14px;
            box-shadow: 0 5px 22px rgba(0, 0, 0, 0.05);
        }

        .receipt-section {
            margin-bottom: 30px;
        }

        .receipt-section h2 {
            color: #641b32;
            font-size: 19px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
            margin: 0 0 18px;
        }

        .receipt-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .receipt-info {
            margin-bottom: 12px;
        }

        .receipt-info span {
            display: block;
            color: #888;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .receipt-info strong {
            color: #333;
            font-size: 15px;
            overflow-wrap: anywhere;
        }

        .receipt-order-number {
            color: #641b32 !important;
        }

        .receipt-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background: #fff2d8;
            color: #8a5a00 !important;
            font-size: 13px !important;
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .receipt-item-name {
            font-weight: 700;
            color: #333;
        }

        .receipt-item-details {
            color: #777;
            font-size: 13px;
            margin-top: 5px;
        }

        .receipt-item-price {
            color: #641b32;
            font-weight: 700;
            white-space: nowrap;
        }

        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 11px 0;
            color: #555;
        }

        .receipt-grand-total {
            border-top: 1px solid #ddd;
            margin-top: 10px;
            padding-top: 18px;
            color: #641b32;
            font-size: 20px;
            font-weight: 800;
        }

        .receipt-note {
            background: #fff8ed;
            border-left: 4px solid #c58a3a;
            padding: 15px;
            border-radius: 5px;
            color: #725326;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .receipt-message {
            padding: 14px 16px;
            border-radius: 7px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
        }

        .receipt-message.success {
            background: #eaf7ed;
            color: #236b35;
            border: 1px solid #b9dfc1;
        }

        .receipt-message.error {
            background: #fff0f0;
            color: #a12626;
            border: 1px solid #efc2c2;
        }

        .receipt-actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .receipt-button {
            display: inline-block;
            text-align: center;
            padding: 13px 23px;
            border-radius: 7px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }

        .receipt-primary {
            background: #641b32;
            color: #fff;
        }

        .receipt-primary:hover {
            background: #481224;
        }

        .receipt-secondary {
            background: #f4e9ec;
            color: #641b32;
        }

        .receipt-secondary:hover {
            background: #ead7dd;
        }

        .receipt-cancel {
            background: #fff;
            color: #b42332;
            border: 1px solid #b42332;
        }

        .receipt-cancel:hover {
            background: #fff0f0;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 25px;
            color: #888;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .receipt-page {
                padding: 20px 12px;
            }

            .receipt-content {
                padding: 20px;
            }

            .receipt-header h1 {
                font-size: 24px;
            }

            .receipt-info-grid {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .receipt-item {
                align-items: flex-start;
            }

            .receipt-grand-total {
                font-size: 18px;
            }
        }

        @media print {
            body {
                background: #fff !important;
            }

            .receipt-page {
                padding: 0;
                background: #fff;
            }

            .receipt-container {
                max-width: 100%;
            }

            .receipt-header,
            .receipt-content {
                box-shadow: none;
            }

            .receipt-actions,
            .receipt-footer {
                display: none !important;
            }

            .receipt-header {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>

</head>

<body>

    <main class="receipt-page">

        <div class="receipt-container">

            <section class="receipt-header">

                <h1>Thank You for Your Order!</h1>

                <p>Here is your order confirmation.</p>

            </section>

            <section class="receipt-content">

                <?php if ($cancelMessage): ?>

                    <div class="receipt-message <?php echo e($cancelMessageType); ?>">
                        <?php echo e($cancelMessage); ?>
                    </div>

                <?php endif; ?>

                <?php if ($checkoutError): ?>

                    <div class="receipt-message error">
                        <?php echo e($checkoutError); ?>
                    </div>

                <?php endif; ?>

                <div class="receipt-section">

                    <h2>Order Information</h2>

                    <div class="receipt-info-grid">

                        <div class="receipt-info">
                            <span>Order Number</span>
                            <strong class="receipt-order-number">
                                <?php echo e($order['order_number']); ?>
                            </strong>
                        </div>

                        <div class="receipt-info">
                            <span>Order Date</span>
                            <strong>
                                <?php echo e(date('d M Y, h:i A', strtotime($order['placed_at']))); ?>
                            </strong>
                        </div>

                        <div class="receipt-info">
                            <span>Order Status</span>
                            <strong class="receipt-status">
                                <?php echo e($orderStatus); ?>
                            </strong>
                        </div>

                        <div class="receipt-info">
                            <span>Payment Status</span>
                            <strong class="receipt-status">
                                <?php echo e($paymentStatus); ?>
                            </strong>
                        </div>

                    </div>

                </div>

                <div class="receipt-section">

                    <h2>Recipient Information</h2>

                    <div class="receipt-info-grid">

                        <div class="receipt-info">
                            <span>Recipient Name</span>
                            <strong>
                                <?php echo e($order['recipient_name']); ?>
                            </strong>
                        </div>

                        <div class="receipt-info">
                            <span>Contact Phone</span>
                            <strong>
                                <?php echo e($order['contact_phone']); ?>
                            </strong>
                        </div>

                        <div class="receipt-info">
                            <span>Delivery Method</span>
                            <strong>
                                <?php echo e($deliveryMethod); ?>
                            </strong>
                        </div>

                        <?php if ($order['delivery_method'] === 'delivery'): ?>

                            <div class="receipt-info">
                                <span>Delivery Address</span>
                                <strong>
                                    <?php echo e($order['delivery_address']); ?>

                                    <?php if (!empty($order['city'])): ?>
                                        , <?php echo e($order['city']); ?>
                                    <?php endif; ?>
                                </strong>
                            </div>

                        <?php endif; ?>

                    </div>

                    <?php if (!empty($order['delivery_notes'])): ?>

                        <div class="receipt-info">
                            <span>Delivery Instructions</span>
                            <strong>
                                <?php echo e($order['delivery_notes']); ?>
                            </strong>
                        </div>

                    <?php endif; ?>

                    <?php if (!empty($order['gift_message'])): ?>

                        <div class="receipt-info">
                            <span>Gift Message</span>
                            <strong>
                                <?php echo e($order['gift_message']); ?>
                            </strong>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="receipt-section">

                    <h2>Your Purchased Gifts</h2>

                    <?php foreach ($orderItems as $item): ?>

                        <div class="receipt-item">

                            <div>

                                <div class="receipt-item-name">
                                    <?php echo e($item['product_name']); ?>
                                </div>

                                <div class="receipt-item-details">
                                    Quantity:
                                    <?php echo (int) $item['quantity']; ?>

                                    ×

                                    <?php echo formatMoney($item['unit_price']); ?>
                                </div>

                            </div>

                            <div class="receipt-item-price">
                                <?php echo formatMoney($item['line_total']); ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <div class="receipt-section">

                    <h2>Payment Summary</h2>

                    <div class="receipt-total-row">
                        <span>Subtotal</span>
                        <strong>
                            <?php echo formatMoney($order['subtotal']); ?>
                        </strong>
                    </div>

                    <div class="receipt-total-row">
                        <span>Delivery Fee</span>
                        <strong>
                            <?php echo formatMoney($order['delivery_fee']); ?>
                        </strong>
                    </div>

                    <div class="receipt-total-row receipt-grand-total">
                        <span>Total</span>
                        <strong>
                            <?php echo formatMoney($order['total_amount']); ?>
                        </strong>
                    </div>

                    <div class="receipt-info" style="margin-top: 18px;">
                        <span>Payment Method</span>
                        <strong>
                            <?php echo e($paymentMethod); ?>
                        </strong>
                    </div>

                </div>

                <?php if ($order['order_status'] === 'cancelled'): ?>

                    <div class="receipt-message error">
                        <strong>Order Cancelled</strong><br>
                        This order has been cancelled.
                    </div>

                <?php elseif (($order['payment_status'] ?? 'pending') !== 'successful'): ?>

                    <div class="receipt-note">
                        <strong>Payment Pending</strong><br>
                        Your order has been recorded, but we have not yet
                        confirmed your payment. Please complete your payment
                        to proceed with your order.
                    </div>

                <?php endif; ?>

                <div class="receipt-actions">

                    <button type="button" class="receipt-button receipt-primary" onclick="window.print()">
                        Print Receipt
                    </button>

                    <a href="../shop/gifts.php" class="receipt-button receipt-secondary">
                        Continue Shopping
                    </a>

                    <?php if ($canRetryPayment): ?>

                        <a href="payment.php?order_id=<?php echo (int) $orderId; ?>" class="receipt-button receipt-primary">
                            Retry Payment
                        </a>

                    <?php endif; ?>

                    <?php if ($canCancel): ?>

                        <form method="POST" action="order_confirmation.php?order_id=<?php echo (int) $orderId; ?>"
                            onsubmit="return confirm('Are you sure you want to cancel this order? Your gifts will be returned to your cart.');">

                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

                            <button type="submit" name="cancel_order" value="1" class="receipt-button receipt-cancel">
                                Cancel Order
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </section>

        </div>

    </main>

</body>

</html>

<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../controllers/CheckoutController.php';
require_once __DIR__ . '/../../controllers/CartController.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount)
{
    return 'GH₵ ' . number_format((float) $amount, 2);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$checkoutController = new CheckoutController();

// Receive selected gifts from the cart.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['selection_action'] ?? '') === 'select_items'
) {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $_SESSION['checkout_error'] = 'Your session has expired. Please refresh your cart.';
        header('Location: ../cart/cart.php');
        exit;
    }

    $submittedIds = $_POST['selected_item_ids'] ?? [];

    if (!is_array($submittedIds) || empty($submittedIds)) {
        $_SESSION['checkout_error'] = 'Please select at least one gift.';
        header('Location: ../cart/cart.php');
        exit;
    }

    $selectedIds = [];

    foreach ($submittedIds as $id) {
        $validId = filter_var($id, FILTER_VALIDATE_INT);

        if ($validId && $validId > 0) {
            $selectedIds[] = (int) $validId;
        }
    }

    $selectedIds = array_values(array_unique($selectedIds));

    if (empty($selectedIds)) {
        $_SESSION['checkout_error'] = 'Please select valid gifts.';
        header('Location: ../cart/cart.php');
        exit;
    }

    $_SESSION['selected_cart_item_ids'] = $selectedIds;

    header('Location: checkout.php');
    exit;
}

// Show any message returned from the cart.
if (!empty($_SESSION['checkout_error'])) {
    $errorMessage = $_SESSION['checkout_error'];
    unset($_SESSION['checkout_error']);
}

// Process the actual order.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !isset($_POST['selection_action'])
) {
    $paymentMethod = $_POST['payment_method'] ?? '';

    // Create the order first.
    $result = $checkoutController->placeOrder(
        $_POST,
        $_POST['csrf_token'] ?? ''
    );

    if (is_array($result) && !empty($result['success'])) {

        $orderId = (int) $result['order_id'];

        // Cash on Delivery does not need Paystack.
        if ($paymentMethod === 'cash_on_delivery') {
            header(
                'Location: order_confirmation.php?order_id=' . $orderId
            );
            exit;
        }

        // Card and Mobile Money both use Paystack.
        $paymentResult = $checkoutController->initializePaystackPayment($orderId);

        if (
            is_array($paymentResult)
            && !empty($paymentResult['success'])
            && !empty($paymentResult['authorization_url'])
        ) {
            header('Location: ' . $paymentResult['authorization_url']);
            exit;
        }

        // Keep the order pending so the customer can retry payment.
        $_SESSION['checkout_error'] = is_array($paymentResult)
            ? ($paymentResult['message'] ?? 'Could not start payment. Please try again.')
            : 'Could not start payment. Please try again.';

        header(
            'Location: order_confirmation.php?order_id=' . $orderId
        );
        exit;
    }

    if (is_array($result)) {
        $errorMessage = $result['message'] ?? 'Unable to place your order. Please try again.';
    } else {
        $errorMessage = is_string($result)
            ? $result
            : 'Unable to place your order. Please try again.';
    }
}

// Get only the selected gifts, including packaging.
$selectedDetails = $checkoutController->getSelectedCartDetails();

$cartItems = $selectedDetails['items'] ?? [];
$cartSummary = $selectedDetails['summary'] ?? [];

$subtotal = (float) ($cartSummary['subtotal'] ?? 0);
$cartCount = (int) ($cartSummary['total_items'] ?? 0);

$deliveryFee = 0;
$totalAmount = $subtotal + $deliveryFee;

$isLoggedIn = !empty($_SESSION['customer_id']);


$selectedIds = $_SESSION['selected_cart_item_ids'] ?? [];

if (
    $isLoggedIn
    && !empty($selectedIds)
    && count($cartItems) !== count($selectedIds)
) {
    $errorMessage = 'Some selected gifts are no longer available in your cart. Please return to your cart and select your gifts again.';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout | Giftly</title>

    <link rel="stylesheet" href="/~fatma.abdourahmane/Giftly/assets/css/style.css?v=5">
    <link rel="stylesheet" href="/~fatma.abdourahmane/Giftly/assets/css/checkout.css?v=1">

    <style>
        .checkout-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .checkout-item-main {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: flex-start;
        }

        .checkout-item-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .checkout-item-details span {
            color: #777;
            font-size: 13px;
        }

        .checkout-item-prices {
            margin-top: 10px;
            padding-left: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 13px;
            color: #777;
        }

        .checkout-item-prices div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .checkout-item-prices .item-total-line {
            color: #7b263b;
            font-weight: 700;
            padding-top: 6px;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>

<header class="checkout-header">

    <a href="../home/Home.php" class="checkout-logo">
        Giftly
        <span>Where Every Gift Tells a Story.</span>
    </a>

    <nav>
        <a href="../shop/gifts.php">Continue Shopping</a>
        <a href="../cart/cart.php">My Cart</a>
    </nav>

</header>

<main class="checkout-container">

    <div class="checkout-heading">
        <p>ALMOST THERE</p>
        <h1>Complete Your Order</h1>
        <span>Provide your details to complete your purchase.</span>
    </div>

    <?php if ($errorMessage !== ''): ?>

        <div class="checkout-error">
            <?php echo e($errorMessage); ?>
        </div>

    <?php endif; ?>

    <?php if (!$isLoggedIn): ?>

        <section class="empty-checkout">

            <h2>Sign in to complete your order</h2>

            <p>
                Your selected gifts are ready.
                Sign in or create an account to continue.
            </p>

            <a href="../login/login.php">Sign In</a>

            <a href="../register/register.php">Create Account</a>

            <a href="../cart/cart.php">Return to Cart</a>

        </section>

    <?php elseif (empty($cartItems) || count($cartItems) !== count($selectedIds)): ?>

        <section class="empty-checkout">

            <h2>No valid gifts selected</h2>

            <p>
                Please return to your cart and select the gifts
                you want to purchase.
            </p>

            <a href="../cart/cart.php">Return to Cart</a>

        </section>

    <?php else: ?>

        <form method="POST" action="checkout.php" class="checkout-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION['csrf_token']); ?>"
            >

            <div class="checkout-layout">

                <!-- CUSTOMER AND DELIVERY DETAILS -->
                <section class="checkout-details">

                    <div class="checkout-card">

                        <h2>Recipient Information</h2>

                        <label for="recipient_name">
                            Recipient's Full Name
                        </label>

                        <input
                            type="text"
                            id="recipient_name"
                            name="recipient_name"
                            maxlength="120"
                            required
                            value="<?php echo e($_POST['recipient_name'] ?? ''); ?>"
                        >

                        <label for="contact_phone">
                            Contact Phone
                        </label>

                        <input
                            type="tel"
                            id="contact_phone"
                            name="contact_phone"
                            maxlength="20"
                            required
                            value="<?php echo e($_POST['contact_phone'] ?? ''); ?>"
                        >

                    </div>

                    <div class="checkout-card">

                        <h2>Delivery Method</h2>

                        <label class="option">

                            <input
                                type="radio"
                                name="delivery_method"
                                value="delivery"
                                <?php echo ($_POST['delivery_method'] ?? 'delivery') === 'delivery' ? 'checked' : ''; ?>
                            >

                            Home Delivery

                        </label>

                        <label class="option">

                            <input
                                type="radio"
                                name="delivery_method"
                                value="pickup"
                                <?php echo ($_POST['delivery_method'] ?? '') === 'pickup' ? 'checked' : ''; ?>
                            >

                            Store Pickup

                        </label>

                        <div id="delivery-fields">

                            <label for="delivery_address">
                                Delivery Address
                            </label>

                            <textarea
                                id="delivery_address"
                                name="delivery_address"
                                rows="3"
                            ><?php echo e($_POST['delivery_address'] ?? ''); ?></textarea>

                            <label for="city">City</label>

                            <input
                                type="text"
                                id="city"
                                name="city"
                                maxlength="80"
                                value="<?php echo e($_POST['city'] ?? ''); ?>"
                            >

                        </div>

                        <label for="delivery_notes">
                            Delivery Instructions (Optional)
                        </label>

                        <textarea
                            id="delivery_notes"
                            name="delivery_notes"
                            rows="2"
                            maxlength="255"
                        ><?php echo e($_POST['delivery_notes'] ?? ''); ?></textarea>

                        <label for="gift_message">
                            Gift Message (Optional)
                        </label>

                        <textarea
                            id="gift_message"
                            name="gift_message"
                            rows="3"
                            maxlength="255"
                            placeholder="Write a special message..."
                        ><?php echo e($_POST['gift_message'] ?? ''); ?></textarea>

                    </div>

                    <div class="checkout-card">

                        <h2>Payment Method</h2>

                        <p>
                            Choose how you would like to pay securely through Paystack.
                        </p>

                        <label class="option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="mobile_money"
                                required
                                <?php echo ($_POST['payment_method'] ?? 'mobile_money') === 'mobile_money' ? 'checked' : ''; ?>
                            >

                            Mobile Money

                        </label>

                        <label class="option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="card"
                                required
                                <?php echo ($_POST['payment_method'] ?? '') === 'card' ? 'checked' : ''; ?>
                            >

                            Debit / Credit Card

                        </label>

                        <label class="option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="cash_on_delivery"
                                required
                                <?php echo ($_POST['payment_method'] ?? '') === 'cash_on_delivery' ? 'checked' : ''; ?>
                            >

                            Cash on Delivery

                        </label>

                    </div>

                </section>

                <!-- ORDER SUMMARY -->
                <aside class="checkout-summary">

                    <h2>Your Selected Gifts</h2>

                    <p>
                        <?php echo $cartCount; ?>
                        selected gift(s)
                    </p>

                    <?php foreach ($cartItems as $item): ?>

                        <?php
                        $quantity = (int) $item['quantity'];
                        $productUnitPrice = (float) $item['price'];
                        $packagingUnitPrice = (float) ($item['packaging_price'] ?? 0);

                        $productTotal = $productUnitPrice * $quantity;
                        $packagingTotal = $packagingUnitPrice * $quantity;
                        $itemTotal = (float) $item['line_total'];
                        ?>

                        <div class="checkout-item">

                            <div class="checkout-item-main">

                                <div class="checkout-item-details">

                                    <strong>
                                        <?php echo e($item['product_name']); ?>
                                    </strong>

                                    <span>
                                        Quantity: <?php echo $quantity; ?>
                                    </span>

                                    <?php if (!empty($item['packaging_name'])): ?>

                                        <span>
                                            Packaging: <?php echo e($item['packaging_name']); ?>
                                        </span>

                                    <?php else: ?>

                                        <span>No packaging</span>

                                    <?php endif; ?>

                                </div>

                                <strong>
                                    <?php echo formatMoney($itemTotal); ?>
                                </strong>

                            </div>

                            <div class="checkout-item-prices">

                                <div>
                                    <span>Gift price</span>
                                    <span><?php echo formatMoney($productTotal); ?></span>
                                </div>

                                <div>
                                    <span>Packaging</span>
                                    <span><?php echo formatMoney($packagingTotal); ?></span>
                                </div>

                                <div class="item-total-line">
                                    <span>Item total</span>
                                    <span><?php echo formatMoney($itemTotal); ?></span>
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                    <div class="checkout-total">

                        <span>Subtotal</span>

                        <strong>
                            <?php echo formatMoney($subtotal); ?>
                        </strong>

                    </div>

                    <div class="checkout-total">

                        <span>Delivery Fee</span>

                        <strong>
                            <?php echo formatMoney($deliveryFee); ?>
                        </strong>

                    </div>

                    <div class="checkout-grand-total">

                        <span>Total</span>

                        <strong>
                            <?php echo formatMoney($totalAmount); ?>
                        </strong>

                    </div>

                    <button
                        type="submit"
                        class="place-order-button"
                    >
                        Place Order
                    </button>

                    <a href="../cart/cart.php" class="back-to-cart">
                        Back to Cart
                    </a>

                </aside>

            </div>

        </form>

    <?php endif; ?>

</main>

<footer class="checkout-footer">

    <p>
        © <?php echo date('Y'); ?> Giftly. Where Every Gift Tells a Story.
    </p>

</footer>

<script src="checkout.js"></script>

</body>
</html>

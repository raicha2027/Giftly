
<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../controllers/CartController.php';
require_once __DIR__ . '/../../config/db.php';

$baseUrl = '/~fatma.abdourahmane/Giftly';

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

$cartController = new CartController();
$cartDetails = $cartController->getCartDetails();

$cartItems = $cartDetails['items'] ?? [];

$cartSummary = $cartDetails['summary'] ?? [
    'total_items' => 0,
    'subtotal' => 0
];

$cartCount = (int) ($cartSummary['total_items'] ?? 0);
$subtotal = (float) ($cartSummary['subtotal'] ?? 0);

// Get available packaging options.
$packagingStmt = $pdo->prepare("
    SELECT packaging_id, packaging_name, packaging_price
    FROM giftly_gift_packaging
    WHERE is_active = 1
    ORDER BY packaging_id ASC
");

$packagingStmt->execute();
$packagingOptions = $packagingStmt->fetchAll(PDO::FETCH_ASSOC);

$successMessage = $_SESSION['cart_success'] ?? '';
$errorMessage = $_SESSION['cart_error'] ?? '';

unset($_SESSION['cart_success'], $_SESSION['cart_error']);

function getCartImageUrl($imagePath)
{
    global $baseUrl;

    if (empty($imagePath)) {
        return '';
    }

    $imagePath = str_replace('\\', '/', trim($imagePath));

    if (preg_match('~^https?://~i', $imagePath)) {
        return $imagePath;
    }

    $imagePath = preg_replace('~^(\.\./)+~', '', $imagePath);
    $imagePath = preg_replace('~^/?assets/~i', '', $imagePath);

    $parts = explode('/', trim($imagePath, '/'));

    return $baseUrl . '/assets/' .
        implode('/', array_map('rawurlencode', $parts));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Cart | Giftly</title>

    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css?v=2">

    <style>
        .gift-selection-box {
            margin: 15px 0;
            padding: 13px 15px;
            background: #f8e9ed;
            border: 1px solid #7b263b;
            border-radius: 8px;
        }

        .gift-selection-box label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            color: #7b263b;
            font-weight: 600;
            font-size: 14px;
        }

        .gift-selection-box input[type="checkbox"] {
            width: 21px;
            height: 21px;
            min-width: 21px;
            cursor: pointer;
            accent-color: #7b263b;
        }

        .gift-selection-box input[type="checkbox"]:disabled {
            cursor: not-allowed;
        }

        .selection-instructions {
            margin: 10px 0 20px;
            color: #666;
            font-size: 14px;
        }

        .selected-gifts-summary {
            margin: 18px 0;
            padding: 15px;
            background: #f8e9ed;
            border-radius: 8px;
        }

        .selected-gifts-summary p {
            margin: 8px 0;
        }

        #selected-subtotal {
            color: #7b263b;
            font-size: 19px;
        }

        #proceed-checkout-button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .packaging-section {
            margin: 16px 0;
            padding: 15px;
            background: #fff8f8;
            border: 1px solid #ead5dc;
            border-radius: 8px;
        }

        .packaging-section h4 {
            margin: 0 0 8px;
            color: #7b263b;
            font-size: 15px;
        }

        .packaging-description {
            margin: 0 0 12px;
            color: #777;
            font-size: 13px;
        }

        .packaging-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .packaging-form select {
            flex: 1;
            min-width: 180px;
            padding: 10px;
            border: 1px solid #d8b8c2;
            border-radius: 6px;
            background: #fff;
            color: #444;
        }

        .packaging-form button {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            background: #7b263b;
            color: #fff;
            cursor: pointer;
        }

        .packaging-form button:hover {
            background: #642034;
        }

        .packaging-current {
            margin-top: 10px;
            color: #7b263b;
            font-size: 13px;
        }

        .cart-item-total {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .packaging-line {
            color: #777;
            font-size: 13px;
        }
    </style>
</head>

<body>

    <header class="cart-header">

        <div class="cart-header-inner">

            <a href="<?php echo $baseUrl; ?>/pages/home/home.php" class="cart-logo">
                Giftly
            </a>

            <nav class="cart-navigation">
                <a href="<?php echo $baseUrl; ?>/pages/home/home.php">Home</a>
                <a href="<?php echo $baseUrl; ?>/pages/shop/gifts.php">Shop</a>
            </nav>

            <div class="cart-header-actions">

                <a href="<?php echo $baseUrl; ?>/pages/dashboard/CustomerDashboard.php">
                My Account
                </a>

                <a href="<?php echo $baseUrl; ?>/pages/cart/cart.php" class="active-cart-link">
                    Cart (<?php echo $cartCount; ?>)
                </a>

            </div>

        </div>

    </header>

    <section class="cart-page-intro">
        <h1>My Shopping Cart</h1>
    </section>

    <main class="cart-container">

        <?php if ($successMessage !== ''): ?>

            <div class="cart-alert success-alert">
                <?php echo e($successMessage); ?>
            </div>

        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>

            <div class="cart-alert error-alert">
                <?php echo e($errorMessage); ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($cartItems)): ?>

            <form
                id="checkout-selection-form"
                method="POST"
                action="<?php echo $baseUrl; ?>/pages/checkout/checkout.php"
            >

                <input
                    type="hidden"
                    name="selection_action"
                    value="select_items"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e($_SESSION['csrf_token']); ?>"
                >

            </form>

            <div class="cart-layout">

                <section class="cart-items-section">

                    <div class="cart-section-heading">
                        <h2>Your Gifts</h2>
                        <span><?php echo $cartCount; ?> item(s)</span>
                    </div>

                    <p class="selection-instructions">
                        Choose the gifts you want to purchase.
                        You can leave the others in your cart for later.
                    </p>

                    <?php foreach ($cartItems as $item): ?>

                        <?php
                        $itemId = (int) ($item['cart_item_id'] ?? 0);
                        $quantity = (int) ($item['quantity'] ?? 1);
                        $stock = (int) ($item['stock_quantity'] ?? 0);
                        $imageUrl = getCartImageUrl($item['image_path'] ?? '');

                        $packagingId = $item['packaging_id'] ?? null;
                        $packagingPrice = (float) ($item['packaging_price'] ?? 0);
                        $productPrice = (float) ($item['price'] ?? 0);
                        $lineTotal = (float) ($item['line_total'] ?? 0);
                        ?>

                        <article class="cart-item">

                            <div class="cart-item-image">

                                <?php if ($imageUrl !== ''): ?>

                                    <img
                                        src="<?php echo e($imageUrl); ?>"
                                        alt="<?php echo e($item['product_name'] ?? 'Gift'); ?>"
                                    >

                                <?php else: ?>

                                    <div class="cart-image-placeholder">
                                        Giftly
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="cart-item-info">

                                <h3>
                                    <?php echo e($item['product_name'] ?? 'Gift'); ?>
                                </h3>

                                <p class="cart-item-unit-price">
                                    <?php echo formatMoney($productPrice); ?> each
                                </p>

                                <div class="gift-selection-box">

                                    <label>

                                        <input
                                            type="checkbox"
                                            name="selected_item_ids[]"
                                            value="<?php echo $itemId; ?>"
                                            form="checkout-selection-form"
                                            class="cart-item-checkbox"
                                            data-price="<?php echo e($lineTotal); ?>"
                                            <?php echo $stock < 1 ? 'disabled' : ''; ?>
                                        >

                                        <span>
                                            <?php echo $stock < 1
                                                ? 'Currently unavailable'
                                                : 'Select this gift to purchase'; ?>
                                        </span>

                                    </label>

                                </div>

                                <div class="packaging-section">

                                    <h4>Make it a special gift</h4>

                                    <form
                                        action="<?php echo $baseUrl; ?>/pages/cart/cart_action.php"
                                        method="POST"
                                        class="packaging-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="set_packaging"
                                        >

                                        <input
                                            type="hidden"
                                            name="cart_item_id"
                                            value="<?php echo $itemId; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo e($_SESSION['csrf_token']); ?>"
                                        >

                                        <select
                                            name="packaging_id"
                                            aria-label="Choose gift packaging"
                                        >

                                            <option
                                                value="0"
                                                <?php echo empty($packagingId) ? 'selected' : ''; ?>
                                            >
                                                No packaging
                                            </option>

                                            <?php foreach ($packagingOptions as $option): ?>

                                                <option
                                                    value="<?php echo (int) $option['packaging_id']; ?>"
                                                    <?php echo (int) $packagingId === (int) $option['packaging_id'] ? 'selected' : ''; ?>
                                                >
                                                    <?php
                                                    echo e($option['packaging_name'])
                                                        . ' '
                                                        . formatMoney($option['packaging_price']);
                                                    ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button type="submit">
                                            Update Packaging
                                        </button>

                                    </form>

                                    <?php if (!empty($item['packaging_name'])): ?>

                                        <p class="packaging-current">
                                            Selected: <?php echo e($item['packaging_name']); ?>
                                            (<?php echo formatMoney($packagingPrice); ?> per gift)
                                        </p>

                                    <?php else: ?>

                                        <p class="packaging-current">
                                            No packaging selected.
                                        </p>

                                    <?php endif; ?>

                                </div>

                                <form
                                    action="<?php echo $baseUrl; ?>/pages/cart/cart_action.php"
                                    method="POST"
                                    class="cart-quantity-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update"
                                    >

                                    <input
                                        type="hidden"
                                        name="cart_item_id"
                                        value="<?php echo $itemId; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo e($_SESSION['csrf_token']); ?>"
                                    >

                                    <label for="quantity-<?php echo $itemId; ?>">
                                        Quantity
                                    </label>

                                    <input
                                        type="number"
                                        id="quantity-<?php echo $itemId; ?>"
                                        name="quantity"
                                        min="1"
                                        max="<?php echo $stock; ?>"
                                        value="<?php echo $quantity; ?>"
                                        required
                                        <?php echo $stock < 1 ? 'disabled' : ''; ?>
                                    >

                                    <button
                                        type="submit"
                                        class="update-button"
                                        <?php echo $stock < 1 ? 'disabled' : ''; ?>
                                    >
                                        Update
                                    </button>

                                </form>

                                <form
                                    action="<?php echo $baseUrl; ?>/pages/cart/cart_action.php"
                                    method="POST"
                                    class="remove-item-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="remove"
                                    >

                                    <input
                                        type="hidden"
                                        name="cart_item_id"
                                        value="<?php echo $itemId; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo e($_SESSION['csrf_token']); ?>"
                                    >

                                    <button type="submit" class="remove-button">
                                        Remove
                                    </button>

                                </form>

                            </div>

                            <div class="cart-item-total">

                                <span>Item Total</span>

                                <strong>
                                    <?php echo formatMoney($lineTotal); ?>
                                </strong>

                                <?php if ($packagingPrice > 0): ?>

                                    <span class="packaging-line">
                                        Product:
                                        <?php echo formatMoney($productPrice * $quantity); ?>
                                    </span>

                                    <span class="packaging-line">
                                        Packaging:
                                        <?php echo formatMoney($packagingPrice * $quantity); ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                    <a
                        href="<?php echo $baseUrl; ?>/pages/shop/gifts.php"
                        class="continue-shopping"
                    >
                        Continue Shopping
                    </a>

                </section>

                <aside class="cart-summary-section">

                    <div class="cart-summary-card">

                        <h2>Order Summary</h2>

                        <div class="summary-row">
                            <span>Items in cart</span>
                            <span><?php echo $cartCount; ?></span>
                        </div>

                        <div class="summary-row">
                            <span>Cart Subtotal</span>
                            <span><?php echo formatMoney($subtotal); ?></span>
                        </div>

                        <div class="summary-row">
                            <span>Delivery</span>
                            <span class="delivery-note">
                                Calculated at checkout
                            </span>
                        </div>

                        <div class="summary-divider"></div>

                        <div class="selected-gifts-summary">

                            <strong>Your Selection</strong>

                            <p>
                                <span id="selected-count">0</span>
                                gift selected
                            </p>

                            <strong id="selected-subtotal">
                                GH₵ 0.00
                            </strong>

                        </div>

                        <button
                            type="submit"
                            form="checkout-selection-form"
                            class="checkout-button"
                            id="proceed-checkout-button"
                            disabled
                        >
                            Proceed to Checkout
                        </button>

                    </div>

                </aside>

            </div>

        <?php else: ?>

            <section class="empty-cart">

                <div class="empty-cart-icon">♡</div>

                <h2>Your cart is waiting for something special.</h2>

                <p>
                    You haven't added any gifts yet.
                </p>

                <a
                    href="<?php echo $baseUrl; ?>/pages/shop/gifts.php"
                    class="empty-cart-button"
                >
                    Discover Gifts
                </a>

            </section>

        <?php endif; ?>

    </main>

    <footer class="cart-footer">

        <p>
            © <?php echo date('Y'); ?> Giftly
        </p>

    </footer>

    <script>
        const checkboxes = document.querySelectorAll('.cart-item-checkbox');

        const checkoutButton = document.getElementById(
            'proceed-checkout-button'
        );

        const selectedCount = document.getElementById('selected-count');

        const selectedSubtotal = document.getElementById(
            'selected-subtotal'
        );

        function updateSelectionSummary() {

            let count = 0;
            let subtotal = 0;

            checkboxes.forEach(function (checkbox) {

                if (checkbox.checked) {
                    count++;

                    subtotal += Number(
                        checkbox.dataset.price || 0
                    );
                }

            });

            selectedCount.textContent = count;

            selectedSubtotal.textContent =
                'GH₵ ' + subtotal.toFixed(2);

            checkoutButton.disabled = count === 0;
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSelectionSummary);
        });

        updateSelectionSummary();
    </script>

    <script src="<?php echo $baseUrl; ?>/pages/cart/cart.js"></script>

</body>

</html>

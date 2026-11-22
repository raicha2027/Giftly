<?php

// GIFTLY - CUSTOMER DASHBOARD CONTROLLER

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';

// 1. CHECK CUSTOMER LOGIN

if (empty($_SESSION['customer_id'])) {
    header('Location: ../login/login.php');
    exit();
}

$customer_id = (int) $_SESSION['customer_id'];

// 2. VERIFY CUSTOMER ACCOUNT

$customerQuery = $pdo->prepare("
    SELECT user_id, first_name, last_name, email
    FROM giftly_users
    WHERE user_id = ?
      AND role_id = 1
      AND is_active = 1
    LIMIT 1
");

$customerQuery->execute([$customer_id]);
$customer = $customerQuery->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION = [];
    session_destroy();

    header('Location: ../login/login.php');
    exit();
}

$customer_name = trim(
    $customer['first_name'] . ' ' . $customer['last_name']
);

$_SESSION['customer_name'] = $customer_name;

// 3. CSRF TOKEN

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. CART COUNT

$cartQuery = $pdo->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0)
    FROM giftly_carts c
    LEFT JOIN giftly_cart_items ci
        ON ci.cart_id = c.cart_id
    WHERE c.user_id = ?
      AND c.status = 'active'
");

$cartQuery->execute([$customer_id]);
$cart_count = (int) $cartQuery->fetchColumn();

// 5. TOTAL ORDERS

$totalOrdersQuery = $pdo->prepare("
    SELECT COUNT(*)
    FROM giftly_orders
    WHERE user_id = ?
");

$totalOrdersQuery->execute([$customer_id]);
$total_orders = (int) $totalOrdersQuery->fetchColumn();

// 6. ORDERS IN PROGRESS

$pendingOrdersQuery = $pdo->prepare("
    SELECT COUNT(*)
    FROM giftly_orders
    WHERE user_id = ?
      AND order_status IN (
          'pending',
          'confirmed',
          'processing',
          'out_for_delivery'
      )
");

$pendingOrdersQuery->execute([$customer_id]);
$pending_orders = (int) $pendingOrdersQuery->fetchColumn();

// 7. DELIVERED ORDERS

$deliveredOrdersQuery = $pdo->prepare("
    SELECT COUNT(*)
    FROM giftly_orders
    WHERE user_id = ?
      AND order_status = 'delivered'
");

$deliveredOrdersQuery->execute([$customer_id]);
$delivered_orders = (int) $deliveredOrdersQuery->fetchColumn();

// 8. RECENT ORDERS

$recentOrdersQuery = $pdo->prepare("
    SELECT
        order_id,
        order_number,
        total_amount,
        order_status,
        placed_at
    FROM giftly_orders
    WHERE user_id = ?
    ORDER BY placed_at DESC
    LIMIT 5
");

$recentOrdersQuery->execute([$customer_id]);
$recent_orders = $recentOrdersQuery->fetchAll(PDO::FETCH_ASSOC);

// 9. FEATURED PRODUCTS

$featuredProductsQuery = $pdo->prepare("
    SELECT
        product_id,
        product_name,
        price,
        image_path
    FROM giftly_products
    WHERE is_active = 1
      AND is_featured = 1
      AND stock_quantity > 0
    ORDER BY created_at DESC
    LIMIT 4
");

$featuredProductsQuery->execute();
$featured_products = $featuredProductsQuery->fetchAll(PDO::FETCH_ASSOC);

?>
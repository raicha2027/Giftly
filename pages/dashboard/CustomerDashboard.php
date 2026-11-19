<?php

// GIFTLY - CUSTOMER DASHBOARD PAGE

require_once __DIR__ . '/../../controllers/CustomerDashboardController.php';

// Safe output helper
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Format currency
function formatMoney($amount)
{
    return 'GH₵ ' . number_format((float) $amount, 2);
}

// Format order status
function formatStatus($status)
{
    return ucfirst(str_replace('_', ' ', (string) $status));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Dashboard | Giftly</title>

    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/customer-dashboard.css?v=4">

</head>

<body>

    <div class="dashboard-container">

        <!-- SIDEBAR -->

        <aside class="dashboard-sidebar" id="dashboardSidebar">

            <div class="sidebar-logo">

                <a href="../home/Home.php">Giftly</a>

                <p>Where Every Gift Tells a Story.</p>

            </div>

            <nav class="sidebar-navigation">

                <a href="../home/Home.php" class="nav-link">
                    <span class="nav-icon">⌂</span>
                    <span>Home</span>
                </a>

                <a href="CustomerDashboard.php" class="nav-link active">
                    <span class="nav-icon">▦</span>
                    <span>Dashboard</span>
                </a>

                <a href="../profile/profile.php" class="nav-link">
                    <span class="nav-icon">♙</span>
                    <span>My Profile</span>
                </a>

                <a href="../shop/gifts.php" class="nav-link">
                    <span class="nav-icon">♡</span>
                    <span>Shop Gifts</span>
                </a>

                <a href="#recent-orders" class="nav-link">
                    <span class="nav-icon">▤</span>
                    <span>My Orders</span>
                </a>

                <a href="../cart/cart.php" class="nav-link">
                    <span class="nav-icon">🛒</span>
                    <span>My Cart</span>

                    <span class="cart-badge">
                        <?php echo $cart_count; ?>
                    </span>
                </a>

            </nav>

            <div class="sidebar-bottom">

                <form action="../../logout.php" method="POST">

                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

                    <button type="submit" class="nav-link logout-link">

                        <span class="nav-icon">↪</span>
                        <span>Logout</span>

                    </button>

                </form>

            </div>

        </aside>


        <!-- MAIN CONTENT -->

        <main class="dashboard-main">


            <!-- TOP BAR -->

            <header class="dashboard-topbar">

                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">
                </button>

                <div class="topbar-title">

                    <p>My Account</p>

                </div>

                <div class="topbar-actions">

                    <a href="../cart/cart.php" class="topbar-cart">

                        Cart

                        <span><?php echo $cart_count; ?></span>

                    </a>

                    <a href="../profile/profile.php" class="customer-mini-profile">

                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($customer_name, 0, 1)); ?>
                        </div>

                        <span>
                            <?php echo e($customer_name); ?>
                        </span>

                    </a>

                </div>

            </header>


            <!-- WELCOME SECTION -->

            <section class="dashboard-welcome">

                <div class="welcome-text">

                    <p class="welcome-label">
                        YOUR GIFTLY SPACE
                    </p>

                    <h1>
                        Welcome back,
                        <?php echo e($customer_name); ?>!
                    </h1>

                    <p>
                        We're happy to have you here.
                        Let's make someone's day special.
                    </p>

                </div>

                <a href="../shop/gifts.php" class="primary-button">
                    Explore Gifts
                </a>

            </section>


            <!-- STATISTICS - WITHOUT COLORED ICON BOXES -->

            <section class="dashboard-statistics">

                <div class="stat-card">

                    <div class="stat-info">

                        <p>Total Orders</p>

                        <h2>
                            <?php echo $total_orders; ?>
                        </h2>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-info">

                        <p>Orders In Progress</p>

                        <h2>
                            <?php echo $pending_orders; ?>
                        </h2>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-info">

                        <p>Delivered Orders</p>

                        <h2>
                            <?php echo $delivered_orders; ?>
                        </h2>

                    </div>

                </div>

            </section>


            <!-- RECENT ORDERS -->

            <section class="dashboard-section recent-orders-section" id="recent-orders">

                <div class="section-heading">

                    <div>

                        <p class="section-label">
                            YOUR PURCHASES
                        </p>

                        <h2>Recent Orders</h2>

                    </div>

                </div>


                <?php if (!empty($recent_orders)): ?>

                    <div class="orders-table-wrapper">

                        <table class="orders-table">

                            <thead>

                                <tr>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($recent_orders as $order): ?>

                                    <tr>

                                        <td>
                                            #<?php echo e($order['order_number']); ?>
                                        </td>

                                        <td>

                                            <?php

                                            $orderDate = strtotime($order['placed_at']);

                                            echo $orderDate
                                                ? date('d M Y', $orderDate)
                                                : 'N/A';

                                            ?>

                                        </td>

                                        <td>
                                            <?php
                                            echo formatMoney($order['total_amount']);
                                            ?>
                                        </td>

                                        <td>

                                            <span class="order-status status-<?php

                                            echo strtolower(
                                                preg_replace(
                                                    '/[^a-z0-9]+/',
                                                    '-',
                                                    $order['order_status']
                                                )
                                            );

                                            ?>">

                                                <?php
                                                echo e(formatStatus($order['order_status']));
                                                ?>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-orders">

                        <div class="empty-orders-icon">
                            ♡
                        </div>

                        <h3>No orders yet</h3>

                        <p>
                            Your gifting journey starts here.
                            Find something special for someone you love.
                        </p>

                    </div>

                <?php endif; ?>

            </section>


            <!-- FEATURED GIFTS -->
            <!-- This section only appears when products exist. -->

            <?php if (!empty($featured_products)): ?>

                <section class="dashboard-section">

                    <div class="featured-products-grid">

                        <?php foreach ($featured_products as $product): ?>

                            <article class="featured-product-card">

                                <div class="product-image-wrapper">

                                    <?php if (!empty($product['image_path'])): ?>

                                        <img src="../../<?php echo e(ltrim($product['image_path'], '/')); ?>"
                                            alt="<?php echo e($product['product_name']); ?>">

                                    <?php else: ?>

                                        <div class="product-image-placeholder">
                                            Giftly
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <div class="featured-product-info">

                                    <h3>
                                        <?php echo e($product['product_name']); ?>
                                    </h3>

                                    <p class="product-price">

                                        <?php
                                        echo formatMoney($product['price']);
                                        ?>

                                    </p>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>


            <!-- GIFT FINDER -->

            <section class="gift-finder-banner">

                <div class="gift-finder-content">

                    <h2>
                        Let us help you find the perfect gift.
                    </h2>

                    <a href="../../gift_agent/gift_finder.php" class="gift-finder-button">
                        Find a Gift for Me
                    </a>

                </div>

            </section>


        </main>

    </div>


    <script src="CustomerDashboard.js"></script>

</body>

</html>
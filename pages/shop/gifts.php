<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/db.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$baseUrl = '/~fatma.abdourahmane/Giftly';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Convert database image path into a usable image URL
|--------------------------------------------------------------------------
*/

function imageUrl($imagePath)
{
    global $baseUrl;

    $imagePath = trim((string) $imagePath);

    if ($imagePath === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $imagePath)) {
        return $imagePath;
    }

    $imagePath = str_replace('\\', '/', $imagePath);
    $imagePath = preg_replace('~^(\.\./)+~', '', $imagePath);
    $imagePath = preg_replace('~^/?assets/~', '', $imagePath);

    return $baseUrl . '/assets/' . implode(
        '/',
        array_map('rawurlencode', explode('/', $imagePath))
    );
}

/*
|--------------------------------------------------------------------------
| Load active gifts from Giftly database tables
|--------------------------------------------------------------------------
*/

$adminProducts = [];

try {
    $sql = "
        SELECT
            p.product_id,
            p.product_name,
            p.brand,
            p.description,
            p.price,
            p.image_path,
            p.stock_quantity,
            c.category_name
        FROM giftly_products p
        LEFT JOIN giftly_categories c
            ON p.category_id = c.category_id
        WHERE p.is_active = 1
        ORDER BY p.product_id DESC
    ";

    $adminProducts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Gift collection loading error: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Calculate cart count
|--------------------------------------------------------------------------
*/

$cartCount = 0;

try {

    if (!empty($_SESSION['customer_id'])) {

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0)
            FROM giftly_cart_items ci
            INNER JOIN giftly_carts c
                ON ci.cart_id = c.cart_id
            WHERE c.user_id = ?
              AND c.status = 'active'
        ");

        $stmt->execute([(int) $_SESSION['customer_id']]);

    } else {

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0)
            FROM giftly_cart_items ci
            INNER JOIN giftly_carts c
                ON ci.cart_id = c.cart_id
            WHERE c.session_id = ?
              AND c.status = 'active'
        ");

        $stmt->execute([session_id()]);
    }

    $cartCount = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Cart count error: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Account information
|--------------------------------------------------------------------------
*/

$isAdmin = isset($_SESSION['admin_id']);
$isCustomer = isset($_SESSION['customer_id']);

$customerName = $_SESSION['customer_name'] ?? 'Customer';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Available Gifts | Giftly</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff8fb;
            color: #333;
            width: 100%;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 14px 5%;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            position: relative;
            z-index: 10;
            width: 100%;
        }

        .logo {
            font-size: 27px;
            font-weight: bold;
            color: #e75480;
            flex-shrink: 0;
        }

        nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            flex-wrap: wrap;
        }

        nav a {
            font-size: 13px;
            font-weight: 500;
            transition: .3s;
        }

        .nav-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(231, 84, 128, .10);
            color: #c94f76;
            padding: 9px 13px;
            border-radius: 20px;
            border: 1px solid rgba(231, 84, 128, .16);
            white-space: nowrap;
            transition: .3s;
        }

        .nav-button:hover {
            background: rgba(231, 84, 128, .22);
            color: #b83d68;
        }

        .account-area {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 5px;
            flex-shrink: 0;
        }

        .profile-icon {
            width: 30px;
            height: 30px;
            background: #e75480;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .profile-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            line-height: 1.2;
        }

        .profile-details strong {
            font-size: 12px;
            color: #292929;
        }

        .profile-details span {
            font-size: 10px;
            color: #888;
        }

        .page-heading {
            text-align: center;
            padding: 45px 20px 25px;
        }

        .page-heading h1 {
            color: #d94f7b;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .page-heading p {
            color: #777;
            font-size: 15px;
        }

        .collection {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 15px 3% 60px;
        }

        .section-title {
            color: #333;
            font-size: 22px;
            margin: 20px 0 22px;
        }

        .gift-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 22px;
            width: 100%;
            align-items: stretch;
        }

        .gift-card {
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .07);
            transition: transform .2s, box-shadow .2s;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
        }

        .gift-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, .10);
        }

        .gift-card img {
            display: block;
            width: 100%;
            height: 230px;
            object-fit: contain;
            object-position: center;
            background: #fff;
            padding: 10px;
        }

        .gift-info {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .category {
            color: #d94f7b;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .gift-info h3 {
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.4;
        }

        .description {
            color: #777;
            font-size: 13px;
            line-height: 1.5;
            min-height: 40px;
            margin-bottom: 12px;
        }

        .price {
            color: #d94f7b;
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 14px;
        }

        .gift-info form {
            margin-top: auto;
        }

        .add-to-cart {
            width: 100%;
            border: none;
            border-radius: 22px;
            padding: 11px 14px;
            background: #e75480;
            color: white;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            margin-top: auto;
        }

        .add-to-cart:hover {
            background: #c94370;
        }

        .add-to-cart:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .empty-message {
            background: white;
            border-radius: 12px;
            padding: 25px;
            color: #777;
            text-align: center;
            grid-column: 1 / -1;
        }

        @media (max-width: 1200px) {
            .gift-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 1000px) {
            header {
                padding: 14px 4%;
            }

            nav {
                gap: 7px;
            }

            nav .nav-button {
                padding: 8px 10px;
                font-size: 12px;
            }

            .gift-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 750px) {
            header {
                flex-wrap: wrap;
                justify-content: center;
            }

            nav {
                justify-content: center;
            }

            .gift-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .collection {
                padding-left: 3%;
                padding-right: 3%;
            }
        }

        @media (max-width: 500px) {
            header {
                flex-direction: column;
                gap: 14px;
                padding: 15px 5%;
            }

            nav {
                gap: 9px;
                justify-content: center;
            }

            nav a {
                font-size: 12px;
            }

            .account-area {
                flex-wrap: wrap;
                justify-content: center;
                margin-left: 0;
            }

            .gift-grid {
                grid-template-columns: 1fr;
            }

            .page-heading h1 {
                font-size: 26px;
            }

            .gift-card img {
                height: 250px;
            }
        }
    </style>

</head>

<body>

    <header>

        <a href="<?= $baseUrl ?>/pages/home/home.php" class="logo">
            Giftly.
        </a>

        <nav>

            <a href="<?= $baseUrl ?>/pages/home/home.php" class="nav-button">
                Home
            </a>

            <a href="<?= $baseUrl ?>/pages/shop/gifts.php" class="nav-button">
                Available Gifts
            </a>

            <a href="<?= $baseUrl ?>/gift_agent/gift_finder.php" class="nav-button">
                Gift Finder
            </a>

            <a href="<?= $baseUrl ?>/pages/cart/cart.php" class="nav-button">
                My Cart (<?= (int) $cartCount ?>)
            </a>

            <?php if ($isCustomer): ?>

                <div class="account-area">

                    <div class="profile-icon">
                        <?= h(strtoupper(substr($customerName, 0, 1))) ?>
                    </div>

                    <div class="profile-details">
                        <strong><?= h($customerName) ?></strong>
                        <span>Customer</span>
                    </div>

                </div>

            <?php elseif ($isAdmin): ?>

                <a href="<?= $baseUrl ?>/admin/products.php" class="nav-button">
                    Manage Products
                </a>

            <?php else: ?>

                <a href="<?= $baseUrl ?>/pages/login/login.php" class="nav-button">
                    Login
                </a>

                <a href="<?= $baseUrl ?>/pages/register/register.php" class="nav-button">
                    Register
                </a>

            <?php endif; ?>

        </nav>

    </header>

    <section class="page-heading">

        <h1>Our Gifts Collection</h1>

        <p>
            Explore our available gifts and find something special.
        </p>

    </section>

    <main class="collection">

        <h2 class="section-title">Available Gifts</h2>

        <div class="gift-grid">

            <?php if (!empty($adminProducts)): ?>

                <?php foreach ($adminProducts as $product): ?>

                    <?php

                    $hasPrice = $product['price'] !== null
                        && $product['price'] !== ''
                        && (float) $product['price'] > 0;

                    $hasStock = (int) $product['stock_quantity'] > 0;

                    ?>

                    <article class="gift-card">

                        <?php if (!empty($product['image_path'])): ?>

                            <img src="<?= h(imageUrl($product['image_path'])) ?>" alt="<?= h($product['product_name']) ?>"
                                loading="lazy">

                        <?php else: ?>

                            <img src="<?= $baseUrl ?>/assets/images/no-image.png" alt="No product image available">

                        <?php endif; ?>

                        <div class="gift-info">

                            <span class="category">
                                <?= h($product['category_name'] ?? 'Gift') ?>
                            </span>

                            <h3>
                                <?= h($product['product_name']) ?>
                            </h3>

                            <p class="description">
                                <?= h($product['description'] ?? '') ?>
                            </p>

                            <?php if ($hasPrice): ?>

                                <p class="price">
                                    GH₵ <?= number_format((float) $product['price'], 2) ?>
                                </p>

                            <?php else: ?>

                                <p class="price">
                                    Price not set
                                </p>

                            <?php endif; ?>

                            <?php if (!$hasPrice): ?>

                                <button type="button" class="add-to-cart" disabled>
                                    Coming Soon
                                </button>

                            <?php elseif (!$hasStock): ?>

                                <button type="button" class="add-to-cart" disabled>
                                    Out of Stock
                                </button>

                            <?php else: ?>

                                <form method="POST" action="<?= $baseUrl ?>/pages/cart/cart_action.php">

                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                                    <input type="hidden" name="action" value="add">

                                    <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">

                                    <input type="hidden" name="quantity" value="1">

                                    <button type="submit" class="add-to-cart">
                                        Add to Cart
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty-message">
                    There are no gifts available at the moment.
                </p>

            <?php endif; ?>

        </div>

    </main>

</body>

</html>
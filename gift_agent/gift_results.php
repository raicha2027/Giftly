<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/CartController.php';

$baseUrl = '/~fatma.abdourahmane/Giftly';

$cartController = new CartController();
$csrfToken = $_SESSION['csrf_token'];

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$preferences = $_SESSION['giftly_preferences'] ?? [];
$results = $_SESSION['giftly_results'] ?? [];

if (empty($preferences) || !is_array($results)) {
    header('Location: ' . $baseUrl . '/gift_agent/gift_finder.php');
    exit;
}

$recommendations = $results['recommendations'] ?? [];

if (!is_array($recommendations)) {
    $recommendations = [];
}

$recommendationMessage = $results['message'] ?? '';

$recommendedProducts = [];

foreach ($recommendations as $recommendation) {

    if (!is_array($recommendation)) {
        continue;
    }

    $productId = $recommendation['product_id'] ?? 0;

    if (!is_numeric($productId) || (int) $productId <= 0) {
        continue;
    }

    $price = $recommendation['price'] ?? 0;

    if (!is_numeric($price) || (float) $price <= 0) {
        continue;
    }

    $image = trim((string) ($recommendation['image_path'] ?? ''));

    $imageUrl = '';

    if ($image !== '') {

        $image = str_replace('\\', '/', $image);
        $image = ltrim($image, '/');

        if (strpos($image, 'assets/') === 0) {
            $image = substr($image, strlen('assets/'));
        }

        if (strpos($image, '..') === false) {
            $imageUrl = $baseUrl . '/assets/' .
                implode('/', array_map('rawurlencode', explode('/', $image)));
        }
    }

    $recommendation['display_image'] = $imageUrl;

    $recommendedProducts[] = $recommendation;
}

$recipient = $preferences['relationship'] ?? 'your recipient';
$occasion = $preferences['occasion'] ?? 'your occasion';
$budget = $preferences['budget'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Your Gift Recommendations | Giftly</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff8fb;
            color: #333;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            padding: 16px 6%;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #8b1e3f;
        }

        nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        nav a {
            color: #8b1e3f;
            font-size: 14px;
            font-weight: bold;
            padding: 10px 16px;
            border-radius: 22px;
            background: #fff0f4;
        }

        nav a:hover {
            background: #8b1e3f;
            color: #fff;
        }

        .page-heading {
            text-align: center;
            padding: 55px 20px 35px;
            background: linear-gradient(135deg, #8b1e3f, #c94f76);
            color: white;
        }

        .page-heading h1 {
            font-size: 34px;
            margin-bottom: 12px;
        }

        .page-heading p {
            font-size: 15px;
            line-height: 1.7;
        }

        .preferences {
            max-width: 1050px;
            margin: 30px auto;
            padding: 22px 25px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.05);
        }

        .preferences h2 {
            color: #8b1e3f;
            font-size: 19px;
            margin-bottom: 15px;
        }

        .preference-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .preference-item {
            background: #fff0f4;
            color: #8b1e3f;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 13px;
        }

        .recommendations {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 25px;
        }

        .section-heading {
            text-align: center;
            margin-bottom: 30px;
        }

        .section-heading h2 {
            color: #8b1e3f;
            font-size: 27px;
            margin-bottom: 10px;
        }

        .section-heading p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
        }

        .gift-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 25px;
        }

        .gift-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            transition: 0.3s;
        }

        .gift-card:hover {
            transform: translateY(-5px);
        }

        .gift-image,
        .image-placeholder {
            width: 100%;
            height: 240px;
            background: #fff0f4;
        }

        .gift-image {
            display: block;
            object-fit: cover;
        }

        .image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b1e3f;
            font-size: 14px;
        }

        .gift-info {
            padding: 20px;
        }

        .gift-info h3 {
            color: #8b1e3f;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .gift-info p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .gift-info .price {
            color: #8b1e3f;
            font-weight: bold;
            font-size: 16px;
        }

        .recommendation-type {
            display: inline-block;
            background: #fff0f4;
            color: #8b1e3f;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .add-to-cart-form {
            margin-top: 18px;
        }

        .add-to-cart-button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 25px;
            background: #8b1e3f;
            color: white;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .add-to-cart-button:hover {
            background: #64152e;
        }

        .empty-message {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            color: #777;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin: 45px 20px 60px;
        }

        .action-button {
            display: inline-block;
            padding: 13px 25px;
            border-radius: 25px;
            background: #8b1e3f;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .action-button.secondary {
            background: #fff0f4;
            color: #8b1e3f;
            border: 1px solid #e9c2ce;
        }

        footer {
            background: #35101e;
            color: white;
            text-align: center;
            padding: 28px 15px;
        }

        footer .footer-logo {
            font-size: 24px;
            font-weight: bold;
            color: #ff9fba;
            margin-bottom: 10px;
        }

        footer p {
            font-size: 13px;
            color: #ead6dd;
            line-height: 1.7;
        }

        @media (max-width: 900px) {
            .gift-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            header {
                flex-direction: column;
            }

            nav {
                justify-content: center;
            }

            .page-heading h1 {
                font-size: 27px;
            }

            .gift-grid {
                grid-template-columns: 1fr;
            }

            .preferences {
                margin: 20px 15px;
            }

            .recommendations {
                padding: 0 15px;
            }
        }
    </style>
</head>

<body>

    <header>
        <a href="<?= $baseUrl ?>/pages/home/home.php" class="logo">Giftly.</a>

        <nav>
            <a href="<?= $baseUrl ?>/pages/home/home.php">Home</a>
            <a href="<?= $baseUrl ?>/pages/shop/gifts.php">Available Gifts</a>
            <a href="<?= $baseUrl ?>/gift_agent/gift_finder.php">Gift Finder</a>
        </nav>
    </header>

    <section class="page-heading">
        <h1>Your Personalised Gift Recommendations</h1>
        <p>We found gift ideas based on the preferences you shared.</p>
    </section>

    <section class="preferences">
        <h2>Your Gift Preferences</h2>

        <div class="preference-details">

            <span class="preference-item">
                Recipient: <?= h($recipient) ?>
            </span>

            <span class="preference-item">
                Occasion: <?= h($occasion) ?>
            </span>

            <?php if ($budget !== ''): ?>
                <span class="preference-item">
                    Budget: GHS <?= h($budget) ?>
                </span>
            <?php endif; ?>

            <?php if (!empty($preferences['interest'])): ?>
                <span class="preference-item">
                    Interest: <?= h($preferences['interest']) ?>
                </span>
            <?php endif; ?>

            <?php if (!empty($preferences['personality'])): ?>
                <span class="preference-item">
                    Personality: <?= h($preferences['personality']) ?>
                </span>
            <?php endif; ?>

        </div>
    </section>

    <main class="recommendations">

        <div class="section-heading">
            <h2>Recommended Gifts</h2>
            <p>These suggestions are based on the information you provided.</p>
        </div>

        <?php if (!empty($recommendationMessage)): ?>
            <p style="text-align:center; margin-bottom:25px; color:#777;">
                <?= h($recommendationMessage) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($recommendedProducts)): ?>

            <div class="gift-grid">

                <?php foreach ($recommendedProducts as $product): ?>

                    <article class="gift-card">

                        <?php if (!empty($product['display_image'])): ?>

                            <img class="gift-image" src="<?= h($product['display_image']) ?>"
                                alt="<?= h($product['product_name'] ?? 'Gift') ?>" loading="lazy">

                        <?php else: ?>

                            <div class="image-placeholder">
                                Image not available
                            </div>

                        <?php endif; ?>

                        <div class="gift-info">

                            <span class="recommendation-type">
                                <?= h($product['recommendation_type'] ?? 'Within your budget') ?>
                            </span>

                            <h3><?= h($product['product_name'] ?? '') ?></h3>

                            <p>
                                <?= h($product['match_reason'] ?? $product['description'] ?? 'A gift selected for you.') ?>
                            </p>

                            <p class="price">
                                GHS <?= h(number_format((float) $product['price'], 2)) ?>
                            </p>

                            <form class="add-to-cart-form" method="POST" action="<?= $baseUrl ?>/pages/cart/cart_action.php">

                                <input type="hidden" name="action" value="add">

                                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">

                                <input type="hidden" name="quantity" value="1">

                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                                <button type="submit" class="add-to-cart-button">
                                    Add to Cart
                                </button>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-message">

                <h3>No suitable gifts found.</h3>

                <p>
                    We could not find products matching your preferences and budget.
                    Try increasing your budget or changing your preferences.
                </p>

            </div>

        <?php endif; ?>

    </main>

    <div class="actions">

        <a href="<?= $baseUrl ?>/pages/shop/gifts.php" class="action-button">
            Browse All Gifts
        </a>

        <a href="<?= $baseUrl ?>/gift_agent/gift_finder.php" class="action-button secondary">
            Try Gift Finder Again
        </a>

    </div>

    <footer>

        <div class="footer-logo">Giftly.</div>

        <p>Making every moment special, one gift at a time.</p>

        <p>&copy; <?= date('Y') ?> Giftly. All rights reserved.</p>

    </footer>

</body>

</html>
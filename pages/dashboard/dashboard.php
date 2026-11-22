<?php

require_once __DIR__ . '/../../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: /~fatma.abdourahmane/Giftly/pages/login/login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Hosting base URL
$baseUrl = '/~fatma.abdourahmane/Giftly';

// Product statistics
$productCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM giftly_products"
)->fetchColumn();

$activeCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM giftly_products WHERE is_active = 1"
)->fetchColumn();

// Customer statistics
$customerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM giftly_users WHERE role_id = 1"
)->fetchColumn();

$activeCustomerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM giftly_users
     WHERE role_id = 1 AND is_active = 1"
)->fetchColumn();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Giftly Admin Dashboard</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/profile-sidebar.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff5f7;
            color: #444;
            width: 100%;
            min-height: 100vh;
        }

        header {
            background: white;
            width: 100%;
            padding: 20px 4%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px #0000000c;
        }

        h1 {
            color: #a64d68;
            font-size: 28px;
        }

        .header-links {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 14px;
        }

        .header-links a {
            text-decoration: none;
            font-weight: bold;
        }

        a.view-store,
        a.logout {
            color: #a64d68;
        }

        a.view-store:hover,
        a.logout:hover {
            color: #bd6b83;
            text-decoration: underline;
        }

        main {
            width: 100%;
            min-height: calc(90vh - 80px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 35px 4%;
        }

        main h2 {
            color: #a64d68;
            font-size: 26px;
            margin-bottom: 10px;
        }

        main>p {
            color: #777;
            font-size: 15px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(210px, 1fr));
            gap: 20px;
            margin-top: 30px;
            width: 100%;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 10px #00000008;
        }

        .card h3 {
            color: #555;
            font-size: 17px;
            margin-bottom: 15px;
        }

        .number {
            font-size: 34px;
            color: #bd6b83;
            font-weight: bold;
        }

        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        a.button {
            display: inline-block;
            background: #bd6b83;
            color: white;
            padding: 14px 24px;
            border-radius: 7px;
            text-decoration: none;
            font-weight: bold;
            transition: .3s;
        }

        a.button:hover {
            background: #a64d68;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px 5%;
                flex-direction: column;
                align-items: flex-start;
            }

            .header-links {
                gap: 8px;
            }

            main {
                padding: 25px 5%;
            }

            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php require_once __DIR__ . '/../../includes/profile-sidebar.php'; ?>

    <header>

        <h1>Giftly Admin</h1>

        <div class="header-links">

            <span>
                Welcome, <?= h($_SESSION['admin_name'] ?? 'Admin') ?>
            </span>

            <span>|</span>

            <a class="view-store" href="<?= $baseUrl ?>/pages/home/home.php">
                View Store
            </a>

            <span>|</span>

            <a class="logout" href="<?= $baseUrl ?>/logout.php">
                Logout
            </a>

        </div>

    </header>

    <main>

        <h2>Dashboard</h2>

        <p>Manage your Giftly store from here.</p>

        <div class="cards">

            <div class="card">
                <h3>Total Products</h3>
                <div class="number"><?= $productCount ?></div>
            </div>

            <div class="card">
                <h3>Active Products</h3>
                <div class="number"><?= $activeCount ?></div>
            </div>

            <div class="card">
                <h3>Total Customers</h3>
                <div class="number"><?= $customerCount ?></div>
            </div>

            <div class="card">
                <h3>Active Customers</h3>
                <div class="number"><?= $activeCustomerCount ?></div>
            </div>

        </div>

        <div class="actions">

            <a class="button" href="<?= $baseUrl ?>/admin/products.php">
                Manage Products
            </a>

            <a class="button" href="<?= $baseUrl ?>/admin/customers.php">
                Manage Customers
            </a>

        </div>

    </main>

</body>

</html>

<?php

require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../pages/login/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        exit('Invalid request. Please go back and try again.');
    }

    $productId = (int) ($_POST['product_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($productId > 0 && $action === 'delete') {

        try {
            $stmt = $pdo->prepare(
                "DELETE FROM giftly_products WHERE product_id = ?"
            );

            $stmt->execute([$productId]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['product_message'] = 'Product deleted successfully.';
                $_SESSION['product_message_type'] = 'success';
            } else {
                $_SESSION['product_message'] = 'Product not found.';
                $_SESSION['product_message_type'] = 'error';
            }

        } catch (PDOException $e) {
            $_SESSION['product_message'] =
                'This product cannot be deleted because it may be linked to an order, cart, or another record.';

            $_SESSION['product_message_type'] = 'error';
        }

        header('Location: products.php');
        exit;
    }
}

// Display messages
if (isset($_SESSION['product_message'])) {

    $message = $_SESSION['product_message'];
    $messageType = $_SESSION['product_message_type'] ?? 'success';

    unset($_SESSION['product_message']);
    unset($_SESSION['product_message_type']);
}

// Get Giftly products and categories
$stmt = $pdo->query(
    "SELECT
        p.product_id,
        p.product_name,
        p.brand,
        p.price,
        p.image_path,
        p.stock_quantity,
        p.is_active,
        c.category_name
     FROM giftly_products p
     LEFT JOIN giftly_categories c
        ON p.category_id = c.category_id
     ORDER BY p.product_id DESC"
);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Escape output
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

    <title>Manage Products - Giftly</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fff5f7;
            color: #444;
        }

        header {
            background: white;
            padding: 20px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px #0000000c;
        }

        header h1 {
            color: #a64d68;
        }

        header a {
            color: #a64d68;
            text-decoration: none;
        }

        main {
            width: 100%;
            padding: 40px 50px;
        }

        .top-actions {
            display: flex;
            gap: 12px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            background: #bd6b83;
            color: white;
            padding: 11px 17px;
            border-radius: 7px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .button:hover {
            background: #a64d68;
        }

        .back {
            background: #777;
        }

        .back:hover {
            background: #555;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin: 18px 0;
        }

        .message.success {
            background: #e7f6e9;
            color: #276738;
        }

        .message.error {
            background: #fde8e8;
            color: #a33333;
        }

        .table-wrap {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            padding: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            color: #a64d68;
        }

        .product-image {
            width: 65px;
            height: 65px;
            object-fit: contain;
            border-radius: 7px;
            background: #fafafa;
        }

        .status-active {
            color: #27834b;
            font-weight: bold;
        }

        .status-inactive {
            color: #b04a4a;
            font-weight: bold;
        }

        .edit-link {
            display: inline-block;
            color: white;
            background: #bd6b83;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 5px;
        }

        .delete-button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background: #f7dddd;
            color: #a33333;
            font-weight: bold;
        }

        .delete-form {
            display: inline;
        }

        @media (max-width: 600px) {
            header {
                padding: 15px;
            }

            main {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Giftly Admin</h1>
    <a href="../logout.php">Logout</a>
</header>

<main>

    <h2>Manage Products</h2>

    <p>
        Add new products and edit or delete existing products in your store.
    </p>

    <div class="top-actions">

        <a class="button" href="add_product.php">
            + Add New Product
        </a>

        <a class="button back" href="../pages/dashboard/dashboard.php">
            Back to Dashboard
        </a>

    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Price (GHS)</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if (count($products) === 0): ?>

                <tr>
                    <td colspan="8">No products have been added yet.</td>
                </tr>

            <?php else: ?>

                <?php foreach ($products as $product): ?>

                    <tr>

                        <td>
                            <?php if (!empty($product['image_path'])): ?>
                                <img
                                    class="product-image"
                                    src="../<?= h($product['image_path']) ?>"
                                    alt="<?= h($product['product_name']) ?>"
                                >
                            <?php else: ?>
                                No image
                            <?php endif; ?>
                        </td>

                        <td><?= h($product['product_name']) ?></td>

                        <td><?= h($product['brand'] ?: '—') ?></td>

                        <td><?= h($product['category_name'] ?: '—') ?></td>

                        <td>
                            <?= number_format((float) $product['price'], 2) ?>
                        </td>

                        <td><?= (int) $product['stock_quantity'] ?></td>

                        <td>
                            <?php if ((int) $product['is_active'] === 1): ?>
                                <span class="status-active">Active</span>
                            <?php else: ?>
                                <span class="status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td>

                            <a
                                class="edit-link"
                                href="edit_product.php?id=<?= (int) $product['product_id'] ?>"
                            >
                                Edit
                            </a>

                            <form
                                method="POST"
                                class="delete-form"
                                onsubmit="return confirm('Are you sure you want to delete this product?');"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int) $product['product_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <button type="submit" class="delete-button">
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>

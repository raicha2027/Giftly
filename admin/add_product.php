<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

$categories = $pdo->query(
    "SELECT category_id, category_name
     FROM giftly_categories
     WHERE is_active = 1
     ORDER BY category_name"
)->fetchAll();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )
    ) {
        exit('Invalid request. Please refresh the page.');
    }

    $name = trim($_POST['product_name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $stock = $_POST['stock_quantity'] ?? '0';
    $active = isset($_POST['is_active']) ? 1 : 0;
    $featured = isset($_POST['is_featured']) ? 1 : 0;

    $recipientGender = $_POST['recipient_gender'] ?? '';
    $interests = trim($_POST['interests'] ?? '');
    $occasions = trim($_POST['occasions'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $recipientAge = trim($_POST['recipient_age'] ?? '');
    $personality = trim($_POST['personality'] ?? '');
    $style = trim($_POST['style'] ?? '');
    $giftType = trim($_POST['gift_type'] ?? '');

    if ($name === '' || strlen($name) > 150) {
        $error = 'Enter a product name (maximum 150 characters).';
    } elseif (strlen($brand) > 100) {
        $error = 'Brand must not exceed 100 characters.';
    } elseif (!is_numeric($price) || (float) $price <= 0) {
        $error = 'Enter a valid price greater than zero.';
    } elseif (!ctype_digit((string) $stock) || (int) $stock < 0) {
        $error = 'Enter a valid stock quantity.';
    } elseif (!in_array($recipientGender, ['men', 'women'], true)) {
        $error = 'Select whether this product is for men or women.';
    } elseif ($interests === '' || $occasions === '') {
        $error = 'Enter at least one interest and one suitable occasion.';
    } else {
        $checkCategory = $pdo->prepare(
            "SELECT COUNT(*) FROM giftly_categories
             WHERE category_id = ? AND is_active = 1"
        );
        $checkCategory->execute([$categoryId]);

        if (!$checkCategory->fetchColumn()) {
            $error = 'Please select a valid category.';
        }
    }

    $imagePath = null;

    if (
        $error === '' &&
        isset($_FILES['product_image']) &&
        $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES['product_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Image upload failed. Please try again.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Image must be smaller than 5 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (
                !isset($allowed[$mime]) ||
                getimagesize($file['tmp_name']) === false
            ) {
                $error = 'Only JPG, PNG and WebP images are allowed.';
            } else {
                $uploadDirectory = __DIR__ . '/../assets/uploads/products/';

                if (!is_dir($uploadDirectory)) {
                    if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                        $error = 'Could not create the product image folder.';
                    }
                }

                if ($error === '') {
                    $filename = bin2hex(random_bytes(16))
                        . '.' . $allowed[$mime];

                    if (
                        move_uploaded_file(
                            $file['tmp_name'],
                            $uploadDirectory . $filename
                        )
                    ) {
                        $imagePath = 'assets/uploads/products/' . $filename;
                    } else {
                        $error = 'Could not save the uploaded image.';
                    }
                }
            }
        }
    }

    if ($error === '') {

        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'product';
        }

        $baseSlug = $slug;
        $number = 1;

        $checkSlug = $pdo->prepare(
            "SELECT COUNT(*) FROM giftly_products WHERE slug = ?"
        );

        while (true) {
            $checkSlug->execute([$slug]);

            if ((int) $checkSlug->fetchColumn() === 0) {
                break;
            }

            $number++;
            $slug = $baseSlug . '-' . $number;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO giftly_products
                (
                    category_id,
                    product_name,
                    brand,
                    slug,
                    description,
                    price,
                    image_path,
                    stock_quantity,
                    is_active,
                    is_featured,
                    recipient_gender,
                    interests,
                    occasions,
                    relationship,
                    recipient_age,
                    personality,
                    style,
                    gift_type
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $categoryId,
                $name,
                $brand !== '' ? $brand : null,
                $slug,
                $description !== '' ? $description : null,
                $price,
                $imagePath,
                (int) $stock,
                $active,
                $featured,
                $recipientGender,
                $interests,
                $occasions,
                $relationship !== '' ? $relationship : null,
                $recipientAge !== '' ? $recipientAge : null,
                $personality !== '' ? $personality : null,
                $style !== '' ? $style : null,
                $giftType !== '' ? $giftType : null
            ]);

            header('Location: products.php?added=1');
            exit;

        } catch (PDOException $e) {

            if ($imagePath !== null) {
                @unlink(__DIR__ . '/../' . $imagePath);
            }

            $error = 'Could not save the product. Please check the database.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Product - Giftly</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fff5f7;
            font-family: Arial, sans-serif;
            color: #444;
        }

        .container {
            max-width: 750px;
            margin: 35px auto;
            padding: 30px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 5px 20px #0000000c;
        }

        h1,
        h2 {
            color: #a64d68;
        }

        h2 {
            margin-top: 30px;
            font-size: 20px;
        }

        label {
            display: block;
            margin-top: 18px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 15px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .check {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .check input {
            width: auto;
        }

        button,
        .back {
            display: inline-block;
            margin-top: 22px;
            padding: 13px 20px;
            border: none;
            border-radius: 7px;
            background: #bd6b83;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .back {
            background: #777;
        }

        .error {
            background: #ffe7e7;
            color: #a00000;
            padding: 12px;
            border-radius: 7px;
        }

        .help {
            color: #888;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>

<body>

    <div class="container">

        <h1>Add New Product</h1>
        <p>Enter the details of the product you want to sell.</p>

        <?php if ($error): ?>
            <p class="error"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if (count($categories) === 0): ?>

            <p class="error">
                No active categories exist. Please create a category first.
            </p>

        <?php else: ?>

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                <label>Product Name *</label>
                <input type="text" name="product_name" maxlength="150" required>

                <label>Brand</label>
                <input type="text" name="brand" maxlength="100" placeholder="Enter brand name">

                <label>Category *</label>
                <select name="category_id" required>
                    <option value="">Choose a category</option>

                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['category_id'] ?>">
                            <?= h($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <label>Product Description and Suitable Recipients</label>
                <textarea name="description"
                    placeholder="Describe the product, who it is suitable for, gender, age group, interests, personality, style and occasions..."></textarea>

                <p class="help">
                    Example: A fashionable handbag for women, suitable for
                    young adults and adult women who enjoy fashion and
                    accessories. A good gift for birthdays, graduations,
                    mothers, sisters and female friends.
                </p>

                <h2>Gift Finder Information</h2>

                <p class="help">
                    Enter these details clearly. They help Gift Finder match
                    products with the recipient's preferences. Separate
                    multiple answers with commas.
                </p>

                <label>Recipient Gender *</label>
                <select name="recipient_gender" required>
                    <option value="">Choose gender</option>
                    <option value="men">Men</option>
                    <option value="women">Women</option>
                </select>

                <label>Interests *</label>
                <input type="text" name="interests" required placeholder="Fashion, Beauty, African Culture">

                <label>Suitable Occasions *</label>
                <input type="text" name="occasions" required placeholder="Birthday, Graduation, Wedding">

                <label>Suitable Relationships</label>
                <input type="text" name="relationship" placeholder="Mother, Father, Friend, Partner">

                <label>Recipient Age Groups</label>
                <input type="text" name="recipient_age" placeholder="Teenager, Adult, Senior">

                <label>Personality</label>
                <input type="text" name="personality" placeholder="Creative, Romantic, Practical">

                <label>Style</label>
                <input type="text" name="style" placeholder="Classic, Modern, Traditional">

                <label>Gift Type</label>
                <input type="text" name="gift_type" placeholder="Personalised, Handmade, Luxury">

                <h2>Price and Inventory</h2>

                <label>Price (GHS) *</label>
                <input type="number" name="price" min="0.01" step="0.01" required>

                <label>Stock Quantity *</label>
                <input type="number" name="stock_quantity" min="0" value="0" required>

                <label>Product Image</label>
                <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                <p class="help">JPG, PNG or WebP. Maximum size: 5 MB.</p>

                <label class="check">
                    <input type="checkbox" name="is_active" checked>
                    Active product
                </label>

                <label class="check">
                    <input type="checkbox" name="is_featured">
                    Featured product
                </label>

                <button type="submit">Save Product</button>
                <a class="back" href="products.php">Cancel</a>

            </form>

        <?php endif; ?>

    </div>

</body>

</html>
<?php

require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../pages/login/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function clean_list($value)
{
    if (is_array($value)) {
        $value = implode(', ', $value);
    }

    return trim((string) $value);
}

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId) {
    die('Invalid product ID.');
}

// Get product from Giftly table
$stmt = $pdo->prepare(
    'SELECT * FROM giftly_products WHERE product_id = ? LIMIT 1'
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    die('Product not found.');
}

// Get categories from Giftly table
$categoryStmt = $pdo->query(
    'SELECT category_id, category_name
     FROM giftly_categories
     ORDER BY category_name ASC'
);

$categories = $categoryStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !is_string($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid security token. Please refresh the page and try again.');
    }

    $productName = trim($_POST['product_name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $categoryId = filter_var(
        $_POST['category_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $price = filter_var(
        $_POST['price'] ?? '',
        FILTER_VALIDATE_FLOAT
    );

    $stockQuantity = filter_var(
        $_POST['stock_quantity'] ?? '',
        FILTER_VALIDATE_INT
    );

    $recipientGender = strtolower(trim($_POST['recipient_gender'] ?? ''));
    $interests = clean_list($_POST['interests'] ?? '');
    $occasions = clean_list($_POST['occasions'] ?? '');
    $relationship = clean_list($_POST['relationship'] ?? '');
    $recipientAge = clean_list($_POST['recipient_age'] ?? '');
    $personality = clean_list($_POST['personality'] ?? '');
    $style = clean_list($_POST['style'] ?? '');
    $giftType = clean_list($_POST['gift_type'] ?? '');

    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    if ($productName === '') {
        $errors[] = 'Product name is required.';
    }

    if (!$categoryId) {
        $errors[] = 'Please select a category.';
    }

    if ($price === false || $price <= 0) {
        $errors[] = 'Enter a valid price greater than zero.';
    }

    if ($stockQuantity === false || $stockQuantity < 0) {
        $errors[] = 'Enter a valid stock quantity.';
    }

    if (!in_array($recipientGender, ['men', 'women'], true)) {
        $errors[] = 'Please select whether this product is for men or women.';
    }

    if ($interests === '') {
        $errors[] = 'Enter at least one suitable interest.';
    }

    if ($occasions === '') {
        $errors[] = 'Enter at least one suitable occasion.';
    }

    // Create a unique slug
    $slug = strtolower(trim($productName));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'product-' . $productId;
    }

    $baseSlug = $slug;
    $counter = 1;

    $slugStmt = $pdo->prepare(
        'SELECT product_id
         FROM giftly_products
         WHERE slug = ? AND product_id != ?
         LIMIT 1'
    );

    while (true) {
        $slugStmt->execute([$slug, $productId]);

        if (!$slugStmt->fetch()) {
            break;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }

    // Keep the existing image unless a new one is uploaded
    $imagePath = $product['image_path'];

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading the image.';

        } else {

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];

            $fileTmp = $_FILES['image']['tmp_name'];
            $fileSize = (int) $_FILES['image']['size'];
            $mimeType = mime_content_type($fileTmp);

            if (!isset($allowedTypes[$mimeType])) {
                $errors[] = 'Only JPG, PNG, WEBP and GIF images are allowed.';

            } elseif ($fileSize > 5 * 1024 * 1024) {
                $errors[] = 'The image must not be larger than 5 MB.';

            } else {

                $uploadDirectory = __DIR__ . '/../assets/uploads/products/';

                if (
                    !is_dir($uploadDirectory) &&
                    !mkdir($uploadDirectory, 0755, true)
                ) {
                    $errors[] = 'Could not create the product image folder.';

                } else {

                    $extension = $allowedTypes[$mimeType];
                    $newFileName = bin2hex(random_bytes(16)) . '.' . $extension;
                    $destination = $uploadDirectory . $newFileName;

                    if (move_uploaded_file($fileTmp, $destination)) {
                        $imagePath = 'assets/uploads/products/' . $newFileName;
                    } else {
                        $errors[] = 'Could not save the uploaded image.';
                    }
                }
            }
        }
    }

    // Update product in Giftly table
    if (empty($errors)) {

        try {

            $updateStmt = $pdo->prepare(
                'UPDATE giftly_products SET
                    category_id = :category_id,
                    product_name = :product_name,
                    brand = :brand,
                    slug = :slug,
                    description = :description,
                    price = :price,
                    image_path = :image_path,
                    stock_quantity = :stock_quantity,
                    is_active = :is_active,
                    is_featured = :is_featured,
                    recipient_gender = :recipient_gender,
                    interests = :interests,
                    occasions = :occasions,
                    relationship = :relationship,
                    recipient_age = :recipient_age,
                    personality = :personality,
                    style = :style,
                    gift_type = :gift_type,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE product_id = :product_id'
            );

            $updateStmt->execute([
                ':category_id' => $categoryId,
                ':product_name' => $productName,
                ':brand' => $brand !== '' ? $brand : null,
                ':slug' => $slug,
                ':description' => $description !== '' ? $description : null,
                ':price' => $price,
                ':image_path' => $imagePath,
                ':stock_quantity' => $stockQuantity,
                ':is_active' => $isActive,
                ':is_featured' => $isFeatured,
                ':recipient_gender' => $recipientGender,
                ':interests' => $interests,
                ':occasions' => $occasions,
                ':relationship' => $relationship,
                ':recipient_age' => $recipientAge,
                ':personality' => $personality,
                ':style' => $style,
                ':gift_type' => $giftType,
                ':product_id' => $productId
            ]);

            // Delete old image only after successful update
            if (
                $imagePath !== $product['image_path'] &&
                !empty($product['image_path'])
            ) {

                $oldImage = basename($product['image_path']);

                $oldImagePath = __DIR__ .
                    '/../assets/uploads/products/' . $oldImage;

                if (is_file($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }

            header('Location: products.php?updated=1');
            exit;

        } catch (PDOException $e) {

            $errors[] = 'Could not update the product. Please check the details and try again.';
        }
    }

    // Keep submitted values if validation fails
    $product['product_name'] = $productName;
    $product['brand'] = $brand;
    $product['description'] = $description;
    $product['category_id'] = $categoryId;
    $product['price'] = $price;
    $product['stock_quantity'] = $stockQuantity;
    $product['recipient_gender'] = $recipientGender;
    $product['interests'] = $interests;
    $product['occasions'] = $occasions;
    $product['relationship'] = $relationship;
    $product['recipient_age'] = $recipientAge;
    $product['personality'] = $personality;
    $product['style'] = $style;
    $product['gift_type'] = $giftType;
    $product['is_active'] = $isActive;
    $product['is_featured'] = $isFeatured;
    $product['image_path'] = $imagePath;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Product | Giftly Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            background: #f6f6f6;
            font-family: Arial, sans-serif;
            color: #222;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        h1 {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font: inherit;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .help {
            margin-top: 6px;
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 12px 0;
        }

        .checkbox-row input {
            width: auto;
        }

        .checkbox-row label {
            margin: 0;
        }

        .current-image {
            max-width: 150px;
            max-height: 150px;
            display: block;
            margin: 10px 0;
            object-fit: contain;
        }

        .errors {
            padding: 12px 18px;
            margin-bottom: 20px;
            background: #fff0f0;
            border: 1px solid #e5aaaa;
            border-radius: 5px;
            color: #a00000;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        button,
        .cancel {
            display: inline-block;
            padding: 12px 20px;
            border: 0;
            border-radius: 5px;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #222;
            color: white;
        }

        .cancel {
            background: #eee;
            color: #222;
        }
    </style>
</head>

<body>

    <div class="container">

        <h1>Edit Product</h1>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="product_name">Product Name</label>
                <input type="text" id="product_name" name="product_name" value="<?= h($product['product_name']) ?>"
                    required>
            </div>

            <div class="form-group">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" value="<?= h($product['brand']) ?>">
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>

                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>

                    <?php foreach ($categories as $category): ?>
                        <option value="<?= h($category['category_id']) ?>" <?= (string) $product['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>>
                            <?= h($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label for="description">Product Description and Suitable Recipients</label>

                <textarea id="description" name="description"
                    placeholder="Describe the product and who it is suitable for..."><?= h($product['description']) ?></textarea>

                <p class="help">
                    Example: A fashionable handbag for women, suitable for young adults
                    and adult women who enjoy fashion and accessories. A good gift for
                    birthdays, graduations, mothers, sisters and female friends.
                </p>
            </div>

            <div class="form-group">
                <label for="price">Price (GHS)</label>

                <input type="number" id="price" name="price" min="0.01" step="0.01" value="<?= h($product['price']) ?>"
                    required>
            </div>

            <div class="form-group">
                <label for="stock_quantity">Stock Quantity</label>

                <input type="number" id="stock_quantity" name="stock_quantity" min="0"
                    value="<?= h($product['stock_quantity']) ?>" required>
            </div>

            <h2>Gift Finder Information</h2>

            <p class="help">
                These details help Gift Finder choose suitable products.
                Separate multiple values with commas.
            </p>

            <div class="form-group">
                <label for="recipient_gender">Suitable Recipient Gender</label>

                <select id="recipient_gender" name="recipient_gender" required>
                    <option value="">Select gender</option>

                    <option value="men" <?= ($product['recipient_gender'] ?? '') === 'men' ? 'selected' : '' ?>>
                        Men
                    </option>

                    <option value="women" <?= ($product['recipient_gender'] ?? '') === 'women' ? 'selected' : '' ?>>
                        Women
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="interests">Suitable Interests</label>

                <input type="text" id="interests" name="interests" value="<?= h($product['interests']) ?>"
                    placeholder="Fashion, beauty, accessories" required>
            </div>

            <div class="form-group">
                <label for="occasions">Suitable Occasions</label>

                <input type="text" id="occasions" name="occasions" value="<?= h($product['occasions']) ?>"
                    placeholder="Birthday, Graduation, Anniversary" required>
            </div>

            <div class="form-group">
                <label for="relationship">Suitable Relationships</label>

                <input type="text" id="relationship" name="relationship" value="<?= h($product['relationship']) ?>"
                    placeholder="Mother, sister, friend, partner">
            </div>

            <div class="form-group">
                <label for="recipient_age">Suitable Age Groups</label>

                <input type="text" id="recipient_age" name="recipient_age" value="<?= h($product['recipient_age']) ?>"
                    placeholder="Teenager, young adult, adult">
            </div>

            <div class="form-group">
                <label for="personality">Suitable Personalities</label>

                <input type="text" id="personality" name="personality" value="<?= h($product['personality']) ?>"
                    placeholder="Creative, romantic, calm, outgoing">
            </div>

            <div class="form-group">
                <label for="style">Suitable Styles</label>

                <input type="text" id="style" name="style" value="<?= h($product['style']) ?>"
                    placeholder="Elegant, casual, modern, traditional">
            </div>

            <div class="form-group">
                <label for="gift_type">Gift Type</label>

                <input type="text" id="gift_type" name="gift_type" value="<?= h($product['gift_type']) ?>"
                    placeholder="Practical, luxury, handmade, romantic">
            </div>

            <div class="form-group">
                <label for="image">Product Image</label>

                <?php if (!empty($product['image_path'])): ?>
                    <img class="current-image" src="../<?= h($product['image_path']) ?>" alt="Current product image">
                <?php endif; ?>

                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">

                <p class="help">
                    Leave this empty to keep the current image. Maximum size: 5 MB.
                </p>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($product['is_active']) ? 'checked' : '' ?>>

                <label for="is_active">Active product</label>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?>>

                <label for="is_featured">Featured product</label>
            </div>

            <div class="buttons">
                <button type="submit">Update Product</button>
                <a class="cancel" href="products.php">Cancel</a>
            </div>

        </form>

    </div>

</body>

</html>
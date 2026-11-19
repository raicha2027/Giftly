<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/db.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$userId = (int) $_SESSION['customer_id'];

$error = '';
$success = '';

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['profile_csrf'] ?? '';

    if (!hash_equals($_SESSION['profile_csrf'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } else {

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $error = 'Please complete all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (
            strlen($firstName) > 60 ||
            strlen($lastName) > 60 ||
            strlen($email) > 150 ||
            strlen($phone) > 20
        ) {
            $error = 'One or more fields are too long.';
        } else {

            $checkEmail = $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE email = ?
                AND user_id != ?
                LIMIT 1
            ");

            $checkEmail->execute([$email, $userId]);

            if ($checkEmail->fetch()) {
                $error = 'This email address is already in use.';
            } else {

                $update = $pdo->prepare("
                    UPDATE users
                    SET first_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?
                    WHERE user_id = ?
                    AND role_id = 1
                    AND is_active = 1
                ");

                $update->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $phone !== '' ? $phone : null,
                    $userId
                ]);

                $_SESSION['customer_name'] = $firstName . ' ' . $lastName;

                $_SESSION['profile_success'] =
                    'Your profile has been updated successfully.';

                header('Location: profile.php');
                exit;
            }
        }
    }
}

if (isset($_SESSION['profile_success'])) {
    $success = $_SESSION['profile_success'];
    unset($_SESSION['profile_success']);
}

$stmt = $pdo->prepare("
    SELECT first_name, last_name, email, phone, created_at
    FROM users
    WHERE user_id = ?
    AND role_id = 1
    AND is_active = 1
    LIMIT 1
");

$stmt->execute([$userId]);
$profile = $stmt->fetch();

if (!$profile) {
    session_destroy();
    header('Location: ../login/login.php');
    exit;
}

$fullName = trim($profile['first_name'] . ' ' . $profile['last_name']);

$initials = strtoupper(
    substr($profile['first_name'], 0, 1) .
    substr($profile['last_name'], 0, 1)
);

$memberSince = date('d M Y', strtotime($profile['created_at']));

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
    <title>My Profile | Giftly</title>

    <link rel="stylesheet" href="../../assets/css/profile-page.css">
</head>

<body>

    <header class="profile-header">
        <a href="../home/Home.php" class="brand">Giftly</a>
        <a href="../home/Home.php" class="back-link">Back to Store</a>
    </header>

    <main class="profile-container">

        <div class="page-heading">
            <h1>My Profile</h1>
            <p>View and manage your account information.</p>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success"><?= h($success) ?></div>
        <?php endif; ?>

        <section class="profile-card">

            <div class="profile-top">

                <div class="avatar">
                    <?= h($initials) ?>
                </div>

                <div class="profile-intro">
                    <h2><?= h($fullName) ?></h2>
                    <span>Customer Account</span>
                </div>

            </div>

            <div class="profile-section-heading">
                Personal Information
            </div>

            <form method="POST" action="profile.php" id="profileForm">

                <input type="hidden" name="profile_csrf" value="<?= h($_SESSION['profile_csrf']) ?>">

                <div class="profile-fields">

                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?= h($profile['first_name']) ?>"
                            maxlength="60" required disabled>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= h($profile['last_name']) ?>"
                            maxlength="60" required disabled>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= h($profile['email']) ?>" maxlength="150"
                            required disabled>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" value="<?= h($profile['phone'] ?? '') ?>"
                            maxlength="20" disabled>
                    </div>

                    <div class="form-group">
                        <label>Account Type</label>
                        <input type="text" value="Customer" disabled>
                    </div>

                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?= h($memberSince) ?>" disabled>
                    </div>

                </div>

                <div class="form-actions">

                    <button type="button" id="editButton" class="edit-button">
                        Edit Profile
                    </button>

                    <div id="editActions" class="edit-actions" style="display: none;">

                        <button type="submit" class="save-button">
                            Save Changes
                        </button>

                        <button type="button" id="cancelButton" class="cancel-button">
                            Cancel
                        </button>

                    </div>

                </div>

            </form>

        </section>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('profileForm');
            const editButton = document.getElementById('editButton');
            const cancelButton = document.getElementById('cancelButton');
            const editActions = document.getElementById('editActions');

            const editableFields = [
                document.getElementById('first_name'),
                document.getElementById('last_name'),
                document.getElementById('email'),
                document.getElementById('phone')
            ];

            let originalValues = {};

            editButton.addEventListener('click', function () {

                originalValues = {};

                editableFields.forEach(function (field) {
                    originalValues[field.name] = field.value;
                    field.disabled = false;
                });

                editButton.style.display = 'none';
                editActions.style.display = 'flex';

                editableFields[0].focus();
            });

            cancelButton.addEventListener('click', function () {

                editableFields.forEach(function (field) {
                    field.value = originalValues[field.name];
                    field.disabled = true;
                });

                editActions.style.display = 'none';
                editButton.style.display = 'inline-block';
            });

            form.addEventListener('submit', function () {
                editableFields.forEach(function (field) {
                    field.disabled = false;
                });
            });

        });
    </script>

</body>

</html>
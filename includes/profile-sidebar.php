<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/db.php';

$profileUserId = null;
$profileRoleId = null;
$profileRole = '';

if (!empty($_SESSION['admin_id'])) {
    $profileUserId = (int) $_SESSION['admin_id'];
    $profileRoleId = 2;
    $profileRole = 'Administrator';
} elseif (!empty($_SESSION['customer_id'])) {
    $profileUserId = (int) $_SESSION['customer_id'];
    $profileRoleId = 1;
    $profileRole = 'Customer';
}

if (!$profileUserId) {
    return;
}

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

$profileError = '';
$profileSuccess = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_profile'])
) {
    $token = $_POST['profile_csrf'] ?? '';

    if (!hash_equals($_SESSION['profile_csrf'], $token)) {
        $profileError = 'Invalid request. Please refresh the page.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $profileError = 'Please complete all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Please enter a valid email address.';
        } elseif (
            strlen($firstName) > 60 ||
            strlen($lastName) > 60 ||
            strlen($email) > 150 ||
            strlen($phone) > 20
        ) {
            $profileError = 'One or more fields are too long.';
        } else {
            $checkEmail = $pdo->prepare("
                SELECT user_id
                FROM giftly_users
                WHERE email = ?
                  AND user_id != ?
                LIMIT 1
            ");

            $checkEmail->execute([$email, $profileUserId]);

            if ($checkEmail->fetch()) {
                $profileError = 'This email address is already in use.';
            } else {
                $update = $pdo->prepare("
                    UPDATE giftly_users
                    SET first_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?
                    WHERE user_id = ?
                      AND role_id = ?
                ");

                $update->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $phone !== '' ? $phone : null,
                    $profileUserId,
                    $profileRoleId
                ]);

                $fullName = $firstName . ' ' . $lastName;

                if ($profileRoleId === 2) {
                    $_SESSION['admin_name'] = $fullName;
                } else {
                    $_SESSION['customer_name'] = $fullName;
                }

                $profileSuccess = 'Profile updated successfully.';
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT first_name, last_name, email, phone, created_at
    FROM giftly_users
    WHERE user_id = ?
      AND role_id = ?
      AND is_active = 1
    LIMIT 1
");

$stmt->execute([$profileUserId, $profileRoleId]);
$profile = $stmt->fetch();

if (!$profile) {
    return;
}

$profileName = trim(
    $profile['first_name'] . ' ' . $profile['last_name']
);

$profileInitials = strtoupper(
    substr($profile['first_name'], 0, 1) .
    substr($profile['last_name'], 0, 1)
);

$profilePhone = $profile['phone'] ?? '';

$profileJoined = date(
    'd M Y',
    strtotime($profile['created_at'])
);

?>

<aside class="giftly-profile-sidebar">

    <div class="profile-brand">
        Giftly
    </div>

    <div class="profile-heading">
        ACCOUNT PROFILE
    </div>

    <div class="profile-avatar">
        <?= htmlspecialchars($profileInitials) ?>
    </div>

    <h2 class="profile-full-name">
        <?= htmlspecialchars($profileName) ?>
    </h2>

    <span class="profile-role">
        <?= htmlspecialchars($profileRole) ?>
    </span>

    <?php if ($profileError): ?>
        <div class="profile-message profile-error">
            <?= htmlspecialchars($profileError) ?>
        </div>
    <?php endif; ?>

    <?php if ($profileSuccess): ?>
        <div class="profile-message profile-success">
            <?= htmlspecialchars($profileSuccess) ?>
        </div>
    <?php endif; ?>

    <div class="profile-divider"></div>

    <form method="POST" class="profile-form" id="profileEditForm">

        <input type="hidden" name="profile_csrf" value="<?= htmlspecialchars($_SESSION['profile_csrf']) ?>">

        <div class="profile-field">
            <label class="profile-label" for="first_name">FIRST NAME</label>
            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($profile['first_name']) ?>"
                maxlength="60" required disabled>
        </div>

        <div class="profile-field">
            <label class="profile-label" for="last_name">LAST NAME</label>
            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($profile['last_name']) ?>"
                maxlength="60" required disabled>
        </div>

        <div class="profile-field">
            <label class="profile-label" for="email">EMAIL ADDRESS</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>"
                maxlength="150" required disabled>
        </div>

        <div class="profile-field">
            <label class="profile-label" for="phone">PHONE NUMBER</label>
            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($profilePhone) ?>" maxlength="20"
                disabled>
        </div>

        <div class="profile-field">
            <span class="profile-label">ACCOUNT TYPE</span>
            <span class="profile-value">
                <?= htmlspecialchars($profileRole) ?>
            </span>
        </div>

        <div class="profile-field">
            <span class="profile-label">MEMBER SINCE</span>
            <span class="profile-value">
                <?= htmlspecialchars($profileJoined) ?>
            </span>
        </div>

        <button type="button" class="profile-edit-button" id="editProfileButton">
            Edit Profile
        </button>

        <div class="profile-edit-actions" id="profileEditActions">

            <button type="submit" name="update_profile" value="1" class="profile-save-button">
                Save Changes
            </button>

            <button type="button" class="profile-cancel-button" id="cancelProfileButton">
                Cancel
            </button>

        </div>

    </form>

</aside>

<script>
    (function () {
        const form = document.getElementById('profileEditForm');
        const editButton = document.getElementById('editProfileButton');
        const cancelButton = document.getElementById('cancelProfileButton');
        const editActions = document.getElementById('profileEditActions');

        if (!form || !editButton || !cancelButton || !editActions) {
            return;
        }

        const fields = form.querySelectorAll(
            'input[name="first_name"], input[name="last_name"], input[name="email"], input[name="phone"]'
        );

        const originalValues = {};

        fields.forEach(function (field) {
            originalValues[field.name] = field.value;
        });

        editButton.addEventListener('click', function () {
            fields.forEach(function (field) {
                field.disabled = false;
            });

            editButton.style.display = 'none';
            editActions.style.display = 'flex';
        });

        cancelButton.addEventListener('click', function () {
            fields.forEach(function (field) {
                field.value = originalValues[field.name];
                field.disabled = true;
            });

            editActions.style.display = 'none';
            editButton.style.display = 'block';
        });
    })();
</script>
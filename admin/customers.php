<?php

require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../pages/login/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

if (
    empty($_SESSION['customers_csrf']) ||
    !is_string($_SESSION['customers_csrf'])
) {
    $_SESSION['customers_csrf'] = bin2hex(random_bytes(32));
}

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Handle customer actions */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['customers_csrf'], $token)
    ) {
        $error = 'Invalid request. Please refresh the page.';
    } else {

        $action = $_POST['action'] ?? '';
        $customerId = (int) ($_POST['customer_id'] ?? 0);

        if ($customerId <= 0) {
            $error = 'Invalid customer.';
        } else {

            // Check customer in Giftly table
            $checkCustomer = $pdo->prepare("
                SELECT user_id
                FROM giftly_users
                WHERE user_id = ?
                AND role_id = 1
                LIMIT 1
            ");

            $checkCustomer->execute([$customerId]);

            if (!$checkCustomer->fetch()) {
                $error = 'Customer not found.';
            } else {

                // Activate or deactivate customer
                if ($action === 'toggle_status') {

                    $statusUpdate = $pdo->prepare("
                        UPDATE giftly_users
                        SET is_active = IF(is_active = 1, 0, 1)
                        WHERE user_id = ?
                        AND role_id = 1
                    ");

                    $statusUpdate->execute([$customerId]);

                    $_SESSION['customers_success'] =
                        'Customer account status updated successfully.';

                    header('Location: customers.php');
                    exit;
                }

                // Edit customer
                if ($action === 'edit') {

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

                        $emailCheck = $pdo->prepare("
                            SELECT user_id
                            FROM giftly_users
                            WHERE email = ?
                            AND user_id != ?
                            LIMIT 1
                        ");

                        $emailCheck->execute([$email, $customerId]);

                        if ($emailCheck->fetch()) {
                            $error = 'This email address is already in use.';

                        } else {

                            $update = $pdo->prepare("
                                UPDATE giftly_users
                                SET first_name = ?,
                                    last_name = ?,
                                    email = ?,
                                    phone = ?
                                WHERE user_id = ?
                                AND role_id = 1
                            ");

                            $update->execute([
                                $firstName,
                                $lastName,
                                $email,
                                $phone !== '' ? $phone : null,
                                $customerId
                            ]);

                            $_SESSION['customers_success'] =
                                'Customer information updated successfully.';

                            header('Location: customers.php');
                            exit;
                        }
                    }
                }
            }
        }
    }
}

/* Success message */

if (isset($_SESSION['customers_success'])) {
    $success = $_SESSION['customers_success'];
    unset($_SESSION['customers_success']);
}

/* Search customers */

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, phone,
               is_active, created_at
        FROM giftly_users
        WHERE role_id = 1
        AND (
            first_name LIKE ?
            OR last_name LIKE ?
            OR email LIKE ?
        )
        ORDER BY created_at DESC
    ");

    $term = '%' . $search . '%';
    $stmt->execute([$term, $term, $term]);

} else {

    $stmt = $pdo->query("
        SELECT user_id, first_name, last_name, email, phone,
               is_active, created_at
        FROM giftly_users
        WHERE role_id = 1
        ORDER BY created_at DESC
    ");
}

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Customer selected for editing */

$editId = (int) ($_GET['edit'] ?? 0);

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'edit' &&
    $error !== ''
) {
    $editId = $customerId;
}

$editCustomer = null;

if ($editId > 0) {

    $editStmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, phone
        FROM giftly_users
        WHERE user_id = ?
        AND role_id = 1
        LIMIT 1
    ");

    $editStmt->execute([$editId]);
    $editCustomer = $editStmt->fetch(PDO::FETCH_ASSOC);

    if (!$editCustomer) {
        $editId = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Manage Customers | Giftly Admin</title>

    <link rel="stylesheet" href="../assets/css/profile-sidebar.css">

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
            min-height: 100vh;
        }

        header {
            background: white;
            padding: 20px 4%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px #0000000c;
        }

        header h1 {
            color: #a64d68;
            font-size: 27px;
        }

        .header-links {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .header-links a {
            color: #a64d68;
            text-decoration: none;
            font-weight: bold;
        }

        main {
            padding: 35px 4%;
        }

        h2 {
            color: #a64d68;
            font-size: 26px;
            margin-bottom: 10px;
        }

        .description {
            color: #777;
            margin-bottom: 25px;
        }

        .message {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #e9f7ed;
            color: #246b38;
        }

        .error {
            background: #fdebed;
            color: #a32636;
        }

        .customer-card,
        .edit-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px #00000008;
            margin-bottom: 25px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .search-form input {
            flex: 1;
            min-width: 200px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        button,
        .button {
            border: none;
            cursor: pointer;
            padding: 11px 17px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }

        .search-button,
        .edit-button,
        .save-button {
            background: #bd6b83;
            color: white;
        }

        .search-button:hover,
        .edit-button:hover,
        .save-button:hover {
            background: #a64d68;
        }

        .clear-button,
        .cancel-button {
            background: #eee;
            color: #555;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #fff5f7;
            color: #a64d68;
        }

        td {
            color: #555;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .active {
            background: #e7f5eb;
            color: #26713c;
        }

        .inactive {
            background: #fdebed;
            color: #a32636;
        }

        .actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .deactivate-button {
            background: #f8e6e9;
            color: #a32636;
        }

        .activate-button {
            background: #e7f5eb;
            color: #26713c;
        }

        .edit-card h3 {
            color: #a64d68;
            margin-bottom: 20px;
        }

        .edit-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(200px, 1fr));
            gap: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: bold;
            color: #555;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 22px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #888;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px 5%;
                flex-direction: column;
                align-items: flex-start;
            }

            main {
                padding: 25px 5%;
            }

            .edit-fields {
                grid-template-columns: 1fr;
            }

            .customer-card,
            .edit-card {
                padding: 17px;
            }
        }
    </style>

</head>

<body>

    <?php require_once __DIR__ . '/../includes/profile-sidebar.php'; ?>

    <header>

        <h1>Giftly Admin</h1>

        <div class="header-links">

            <span>
                Welcome, <?= h($_SESSION['admin_name'] ?? 'Admin') ?>
            </span>

            <span>|</span>

            <a href="../pages/dashboard/dashboard.php">Dashboard</a>

            <span>|</span>

            <a href="../pages/home/home.php">View Store</a>

            <span>|</span>

            <a href="../logout.php">Logout</a>

        </div>

    </header>

    <main>

        <h2>Manage Customers</h2>

        <p class="description">
            View and manage registered Giftly customers.
        </p>

        <?php if ($success): ?>
            <div class="message success">
                <?= h($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($editCustomer): ?>

            <section class="edit-card">

                <h3>Edit Customer Information</h3>

                <form method="POST" action="customers.php?edit=<?= (int) $editCustomer['user_id'] ?>">

                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['customers_csrf']) ?>">

                    <input type="hidden" name="customer_id" value="<?= (int) $editCustomer['user_id'] ?>">

                    <input type="hidden" name="action" value="edit">

                    <div class="edit-fields">

                        <div class="form-group">
                            <label for="first_name">First Name</label>

                            <input type="text" id="first_name" name="first_name"
                                value="<?= h($editCustomer['first_name']) ?>" maxlength="60" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>

                            <input type="text" id="last_name" name="last_name" value="<?= h($editCustomer['last_name']) ?>"
                                maxlength="60" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>

                            <input type="email" id="email" name="email" value="<?= h($editCustomer['email']) ?>"
                                maxlength="150" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>

                            <input type="text" id="phone" name="phone" value="<?= h($editCustomer['phone'] ?? '') ?>"
                                maxlength="20">
                        </div>

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="save-button">
                            Save Changes
                        </button>

                        <a href="customers.php" class="button cancel-button">
                            Cancel
                        </a>

                    </div>

                </form>

            </section>

        <?php endif; ?>

        <section class="customer-card">

            <form method="GET" action="customers.php" class="search-form">

                <input type="text" name="search" placeholder="Search by customer name or email..."
                    value="<?= h($search) ?>">

                <button type="submit" class="search-button">
                    Search
                </button>

                <a href="customers.php" class="button clear-button">
                    Clear
                </a>

            </form>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer Name</th>
                            <th>Email Address</th>
                            <th>Phone Number</th>
                            <th>Member Since</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($customers) > 0): ?>

                            <?php foreach ($customers as $index => $customer): ?>

                                <tr>

                                    <td><?= $index + 1 ?></td>

                                    <td>
                                        <?= h($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                    </td>

                                    <td><?= h($customer['email']) ?></td>

                                    <td>
                                        <?= h($customer['phone'] ?? 'Not provided') ?>
                                    </td>

                                    <td>
                                        <?= !empty($customer['created_at'])
                                            ? h(date('d M Y', strtotime($customer['created_at'])))
                                            : '—' ?>
                                    </td>

                                    <td>

                                        <?php if ((int) $customer['is_active'] === 1): ?>

                                            <span class="status active">Active</span>

                                        <?php else: ?>

                                            <span class="status inactive">Inactive</span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <a class="button edit-button"
                                                href="customers.php?edit=<?= (int) $customer['user_id'] ?>">
                                                Edit
                                            </a>

                                            <form method="POST" action="customers.php"
                                                onsubmit="return confirm('Are you sure you want to change this customer account status?');">

                                                <input type="hidden" name="csrf_token"
                                                    value="<?= h($_SESSION['customers_csrf']) ?>">

                                                <input type="hidden" name="customer_id"
                                                    value="<?= (int) $customer['user_id'] ?>">

                                                <input type="hidden" name="action" value="toggle_status">

                                                <?php if ((int) $customer['is_active'] === 1): ?>

                                                    <button type="submit" class="deactivate-button">
                                                        Deactivate
                                                    </button>

                                                <?php else: ?>

                                                    <button type="submit" class="activate-button">
                                                        Reactivate
                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="empty">
                                    No customers found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</body>

</html>
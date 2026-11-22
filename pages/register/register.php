<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $result = $auth->registerUser($_POST);

    if ($result === true) {

        $_SESSION['success_message'] =
            'Your account has been created successfully. Please log in.';

        header('Location: ../login/login.php');
        exit();
    }

    $error = $result;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Giftly</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            min-height: 100vh;
            background: #fff8fa;
            color: #333;
        }

        .register-container {
            min-height: 100vh;
            display: flex;
        }

        /* LEFT BURGUNDY PANEL */

        .welcome-panel {
            width: 42%;
            background: #6e263b;
            color: white;
            padding: 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
        }

        .brand {
            width: 100%;
            text-align: left;
        }

        .brand img {
            width: 135px;
            height: auto;
            object-fit: contain;
        }

        .welcome-content {
            max-width: 390px;
            margin: auto 0;
        }

        .welcome-content h1 {
            font-size: 35px;
            line-height: 1.3;
            margin-bottom: 18px;
        }

        .welcome-content p {
            font-size: 15px;
            line-height: 1.8;
            color: #f3dfe5;
        }

        .gift-image {
            width: 100%;
            max-width: 330px;
            height: 280px;
            object-fit: contain;
            margin: 25px auto;
        }

        .welcome-footer {
            font-size: 12px;
            color: #edcbd5;
        }

        /* RIGHT REGISTER PANEL */

        .form-panel {
            width: 58%;
            padding: 40px 7%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff8fa;
        }

        .mobile-logo {
            display: none;
        }

        .form-heading {
            margin-bottom: 30px;
        }

        .form-heading h2 {
            color: #6e263b;
            font-size: 30px;
            margin-bottom: 10px;
        }

        .form-heading p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
        }

        .error-message {
            background: #fff0f0;
            color: #b42318;
            border: 1px solid #f5c2c2;
            padding: 13px 15px;
            border-radius: 6px;
            margin-bottom: 22px;
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            font-weight: bold;
            color: #444;
            margin-bottom: 9px;
        }

        .form-group input {
            width: 100%;
            height: 48px;
            padding: 0 13px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            background: white;
        }

        .form-group input:focus {
            border-color: #6e263b;
            box-shadow: 0 0 0 2px rgba(110, 38, 59, 0.08);
        }

        .password-note {
            color: #888;
            font-size: 12px;
            margin-top: 7px;
        }

        .form-actions {
            margin-top: 30px;
        }

        .register-button {
            width: 100%;
            background: #6e263b;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .register-button:hover {
            background: #511a2b;
        }

        .login-text {
            color: #777;
            font-size: 14px;
            text-align: center;
            margin-top: 22px;
        }

        .login-text a {
            color: #6e263b;
            font-weight: bold;
            text-decoration: none;
        }

        .login-text a:hover {
            text-decoration: underline;
        }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #6e263b;
            font-size: 13px;
            text-decoration: none;
        }

        .back-home:hover {
            text-decoration: underline;
        }

        /* TABLETS */

        @media (max-width: 900px) {
            .welcome-panel {
                width: 38%;
                padding: 30px 22px;
            }

            .form-panel {
                width: 62%;
                padding: 35px 5%;
            }

            .welcome-content h1 {
                font-size: 28px;
            }

            .gift-image {
                max-width: 260px;
            }
        }

        /* MOBILE */

        @media (max-width: 650px) {
            .register-container {
                flex-direction: column;
            }

            .welcome-panel {
                display: none;
            }

            .form-panel {
                width: 100%;
                min-height: 100vh;
                padding: 30px 7%;
            }

            .mobile-logo {
                display: block;
                text-align: center;
                margin-bottom: 30px;
            }

            .mobile-logo img {
                width: 125px;
                height: auto;
            }

            .form-heading h2 {
                font-size: 26px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }
        }
    </style>
</head>

<body>

    <main class="register-container">

        <!-- LEFT BURGUNDY PANEL -->

        <section class="welcome-panel">

            <div class="brand">
                <a href="../home/Home.php">
                    <img src="../../assets/Logo.png" alt="Giftly Logo">
                </a>
            </div>

            <div class="welcome-content">

                <h1>Make Every Gift Special</h1>

                <p>
                    Welcome to Giftly! Discover thoughtful gifts,
                    find something meaningful, and make every
                    occasion memorable.
                </p>

                <img src="../../assets/gift-opening.svg" alt="Gift box illustration" class="gift-image">

                <p>
                    A little thought can make a big difference.
                </p>

            </div>

            <div class="welcome-footer">
                © <?php echo date('Y'); ?> Giftly. All rights reserved.
            </div>

        </section>

        <!-- RIGHT REGISTRATION FORM -->

        <section class="form-panel">

            <div class="mobile-logo">
                <a href="../home/Home.php">
                    <img src="../../assets/Logo.png" alt="Giftly Logo">
                </a>
            </div>

            <div class="form-heading">

                <h2>Create Your Account</h2>

                <p>
                    Join Giftly to explore gifts and make your
                    shopping experience easier.
                </p>

            </div>

            <?php if (!empty($error)): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <div class="form-grid">

                    <div class="form-group">
                        <label for="first_name">First Name</label>

                        <input type="text" id="first_name" name="first_name"
                            value="<?= htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>

                        <input type="text" id="last_name" name="last_name"
                            value="<?= htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>

                        <input type="email" id="email" name="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>

                        <input type="text" id="phone" name="phone"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>

                        <input type="password" id="password" name="password" minlength="8" required>

                        <span class="password-note">
                            At least 8 characters
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>

                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="register-button">
                        Create Account
                    </button>

                    <p class="login-text">
                        Already have an account?
                        <a href="../login/login.php">Log in</a>
                    </p>

                    <a href="../home/home.php" class="back-home">
                        Back to Home
                    </a>

                </div>

            </form>

        </section>

    </main>

</body>

</html>
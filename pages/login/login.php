<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/db.php';

$error = '';

$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT user_id, role_id, first_name, last_name,
                email, password_hash
         FROM giftly_users
         WHERE email = ?
         AND is_active = 1
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {

        $error = 'Account not found or account is inactive.';

    } elseif (!password_verify($password, $user['password_hash'])) {

        $error = 'The password does not match this account.';

    } elseif ((int) $user['role_id'] === 2) {

        session_regenerate_id(true);

        $_SESSION['admin_id'] = $user['user_id'];
        $_SESSION['admin_name'] =
            $user['first_name'] . ' ' . $user['last_name'];

        header('Location: ../dashboard/dashboard.php');
        exit();

    } elseif ((int) $user['role_id'] === 1) {

        session_regenerate_id(true);

        $_SESSION['customer_id'] = $user['user_id'];
        $_SESSION['customer_name'] =
            $user['first_name'] . ' ' . $user['last_name'];

        header('Location: ../home/home.php');
        exit();

    } else {

        $error = 'This account does not have a valid role.';

    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | Giftly</title>

    <link rel="stylesheet" href="../../assets/css/style.css?v=9">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
        }

        body {
            overflow-x: hidden;
            font-family: 'DM Sans', sans-serif;
        }

        /* MAIN PAGE */

        .login-page {
            display: flex;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: hidden;
        }

        /* LEFT BRAND PANEL */

        .brand-panel {
            position: relative;
            flex: 0 0 50%;
            width: 50%;
            min-height: 100vh;
            padding: 30px 45px;
            overflow: hidden;
            background: linear-gradient(110deg, #803650, #64263f);
        }

        .brand-content {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: calc(100vh - 60px);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .brand-header {
            width: 100%;
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            display: block;
            width: 185px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
            margin: 0;
            filter: brightness(0) invert(1);
        }

        /* BRAND MESSAGE */

        .brand-message {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 520px;
            margin-top: clamp(200px, 10vh, 100px);
            text-align: left;
        }

        .small-heading {
            display: block;
            color: #ead5df;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 3px;
            margin-bottom: 15px;
        }

        .brand-message h1 {
            color: #ffffff;
            font-size: clamp(30px, 3.2vw, 44px);
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 16px;
        }

        .brand-message p {
            max-width: 420px;
            color: #f0e2e8;
            font-size: 14px;
            line-height: 1.8;
            margin: 0;
        }

        /* GIFT ILLUSTRATIONS */

        .gift-showcase {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
            overflow: visible !important;
            background: transparent !important;
            box-shadow: none !important;
            pointer-events: none;
        }

        /* WIND CHIMES */

        .windchimes {
            position: absolute !important;
            top: -30px !important;
            right: -150px !important;
            width: 200% !important;
            height: 42% !important;
            max-width: none !important;
            object-fit: contain !important;
            object-position: right top !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        /* GIFT BOXES */

        .gift-boxes {
            position: absolute !important;
            right: -200px !important;
            bottom: -20px !important;
            width: 100% !important;
            height: 48% !important;
            display: flex !important;
            justify-content: flex-end !important;
            align-items: flex-end !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .gift-boxes img {
            display: block !important;
            width: 300% !important;
            max-width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            object-position: right bottom !important;
            transform: none !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        /* RIGHT LOGIN PANEL */

        .form-panel {
            position: relative;
            flex: 0 0 50%;
            width: 50%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px clamp(30px, 5vw, 75px);
            background: #ffffff;
            overflow-y: auto;
        }

        .login-content {
            width: 100%;
            max-width: 450px;
            margin: auto;
        }

        .top-label {
            color: #8a3b55;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3px;
            margin-bottom: 13px;
        }

        .form-panel h2 {
            color: #4f2636;
            font-family: 'DM Sans', sans-serif;
            font-size: 34px;
            font-weight: 600;
            margin: 0 0 12px;
        }

        .description {
            color: #88777b;
            font-size: 14px;
            margin: 0 0 30px;
        }

        /* FORM */

        .form-panel form {
            width: 100%;
            max-width: 100%;
            margin: 0;
        }

        .form-group {
            width: 100%;
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            color: #563942;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .form-panel input:not([type="checkbox"]) {
            display: block;
            width: 100%;
            height: 49px;
            border: 1px solid #e5d7da;
            border-radius: 7px;
            padding: 0 14px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            background: #fffdfd;
        }

        .form-panel input:not([type="checkbox"]):focus {
            border-color: #9b5369;
            box-shadow: 0 0 0 3px rgba(155, 83, 105, 0.10);
        }

        .password-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .password-heading a {
            color: #8a3b55;
            font-size: 12px;
            text-decoration: none;
            white-space: nowrap;
        }

        .password-heading a:hover {
            text-decoration: underline;
        }

        /* REMEMBER ME */

        .remember {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #6d5c61;
            font-size: 13px;
            margin: 4px 0 23px;
            cursor: pointer;
        }

        .remember input {
            width: 15px;
            height: 15px;
            accent-color: #762d46;
        }

        /* SIGN IN BUTTON */

        .login-button {
            display: block;
            width: 100%;
            height: 49px;
            border: none;
            border-radius: 7px;
            background: #762d46;
            color: #ffffff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .login-button:hover {
            background: #572033;
        }

        /* REGISTER LINK */

        .register-text {
            text-align: center;
            margin-top: 25px;
            color: #88777b;
            font-size: 13px;
        }

        .register-text a {
            color: #8a3b55;
            font-weight: 600;
            text-decoration: none;
        }

        .register-text a:hover {
            text-decoration: underline;
        }

        /* MESSAGES */

        .login-error,
        .login-success {
            border-radius: 6px;
            padding: 11px 13px;
            margin: 0 0 18px;
            font-size: 13px;
        }

        .login-error {
            color: #b00020;
            background: #fff0f2;
            border: 1px solid #f1c4cc;
        }

        .login-success {
            color: #236b42;
            background: #effaf2;
            border: 1px solid #c7e8d0;
        }

        /* FOOTER */

        .form-footer {
            width: 100%;
            text-align: center;
            margin-top: 25px;
            color: #a18e94;
            font-size: 11px;
        }

        /* SHORT DESKTOP SCREENS */

        @media (max-height: 750px) and (min-width: 851px) {

            .brand-panel {
                padding-top: 20px;
                padding-bottom: 20px;
            }

            .brand-content {
                min-height: calc(100vh - 40px);
            }

            .brand-logo {
                width: 155px;
            }

            .brand-message {
                margin-top: 45px;
            }

            .brand-message h1 {
                font-size: 35px;
                margin-bottom: 10px;
            }

            .brand-message p {
                font-size: 13px;
                line-height: 1.5;
            }

            .small-heading {
                margin-bottom: 9px;
            }

            .windchimes {
                width: 60% !important;
                height: 45% !important;
            }

            .gift-boxes {
                height: 50% !important;
            }

            .form-panel {
                padding-top: 25px;
                padding-bottom: 25px;
            }

            .description {
                margin-bottom: 22px;
            }

            .form-group {
                margin-bottom: 17px;
            }
        }

        /* TABLET AND MOBILE */

        @media (max-width: 850px) {

            .login-page {
                flex-direction: column;
                min-height: 100vh;
                overflow: visible;
            }

            .brand-panel {
                flex: none;
                width: 100%;
                min-height: 440px;
                height: 440px;
                padding: 28px 30px;
            }

            .brand-content {
                min-height: 100%;
            }

            .brand-logo {
                width: 155px;
            }

            .brand-message {
                margin-top: 38px;
            }

            .brand-message h1 {
                font-size: 34px;
            }

            .windchimes {
                top: 10px !important;
                right: -10px !important;
                width: 55% !important;
                height: 45% !important;
            }

            .gift-boxes {
                right: -10px !important;
                bottom: 0 !important;
                width: 65% !important;
                height: 50% !important;
            }

            .form-panel {
                flex: none;
                width: 100%;
                min-height: 550px;
                padding: 45px 25px;
                overflow: visible;
            }

            .login-content {
                max-width: 450px;
            }
        }

        @media (max-width: 450px) {

            .brand-panel {
                min-height: 400px;
                height: 400px;
                padding: 25px 22px;
            }

            .brand-logo {
                width: 140px;
            }

            .brand-message {
                margin-top: 35px;
            }

            .brand-message h1 {
                font-size: 29px;
            }

            .brand-message p {
                font-size: 12px;
                max-width: 310px;
            }

            .windchimes {
                top: 10px !important;
                right: -8px !important;
                width: 52% !important;
                height: 42% !important;
            }

            .gift-boxes {
                right: -8px !important;
                bottom: 0 !important;
                width: 65% !important;
                height: 48% !important;
            }

            .form-panel {
                padding: 40px 22px;
            }

            .form-panel h2 {
                font-size: 30px;
            }
        }
    </style>

</head>

<body>

    <div class="login-page">

        <!-- LEFT BRAND SECTION -->

        <section class="brand-panel">

            <div class="brand-content">

                <div class="brand-header">

                    <img src="../../assets/Logo.png" alt="Giftly" class="brand-logo">

                </div>

                <div class="brand-message">

                    <span class="small-heading">
                        THE ART OF GIFTING
                    </span>

                    <h1>
                        Find the right gift.<br>
                        Make it memorable.
                    </h1>

                    <p>
                        Discover thoughtful gifts for birthdays,
                        celebrations and all the special people
                        in your life.
                    </p>

                </div>

                <div class="gift-showcase">

                    <img src="../../assets/Christmas%20wind%20chimes.svg" alt="Christmas wind chimes"
                        class="windchimes">

                    <div class="gift-boxes">

                        <img src="../../assets/gift-opening.svg" alt="Gift boxes opening">

                    </div>

                </div>

            </div>

        </section>

        <!-- RIGHT LOGIN FORM -->

        <main class="form-panel">

            <div class="login-content">

                <div class="top-label">
                    WELCOME BACK
                </div>

                <h2>Sign in to Giftly</h2>

                <p class="description">
                    Welcome back! Please enter your details.
                </p>

                <?php if ($success !== ''): ?>

                    <p class="login-success">
                        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    </p>

                <?php endif; ?>

                <?php if ($error !== ''): ?>

                    <p class="login-error">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </p>

                <?php endif; ?>

                <form action="" method="POST">

                    <div class="form-group">

                        <label for="email">Email address</label>

                        <input type="email" id="email" name="email" placeholder="Enter your email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

                    </div>

                    <div class="form-group">

                        <div class="password-heading">

                            <label for="password">Password</label>

                            <a href="#">Forgot password?</a>

                        </div>

                        <input type="password" id="password" name="password" placeholder="Enter your password" required>

                    </div>

                    <label class="remember">

                        <input type="checkbox" name="remember">

                        <span>Remember me</span>

                    </label>

                    <button type="submit" class="login-button">
                        Sign in
                    </button>

                </form>

                <div class="register-text">

                    Don't have an account?

                    <a href="../register/register.php">
                        Create an account
                    </a>

                </div>

            </div>

            <footer class="form-footer">
                © 2026 Giftly. All rights reserved.
            </footer>

        </main>

    </div>

</body>

</html>
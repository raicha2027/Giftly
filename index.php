<?php

require_once __DIR__ . '/includes/auth_check.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giftly - Customer Account</title>
</head>

<body>

    <h1>Welcome to Giftly!</h1>

    <p>
        Hello,
        <?php echo htmlspecialchars($_SESSION['first_name']); ?>!
    </p>

    <p>You have successfully logged in to your Giftly account.</p>

    <p>
        <a href="pages/home/home.php">Browse the Giftly storefront</a>
    </p>

    <p>
        Email:
        <?php echo htmlspecialchars($_SESSION['email']); ?>
    </p>

</body>

</html>
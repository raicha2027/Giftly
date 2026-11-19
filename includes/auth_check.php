<?php

require_once __DIR__ . '/session.php';

if (empty($_SESSION['customer_id'])) {

    $_SESSION['error_message'] = "Please log in to access this page.";

    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pagesPosition = strpos($scriptPath, '/pages/');

    $appPath = $pagesPosition !== false
        ? substr($scriptPath, 0, $pagesPosition)
        : rtrim(dirname($scriptPath), '/.');

    header("Location: " . $appPath . "/pages/login/login.php");
    exit();
}

?>
<?php

if (!isset($_SESSION['user_id'])) {
    header("Location: /subsystem1/login");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 56px);">
        <div class="text-center">
            <h1 class="display-4">Welcome Back<?php if(isset($_SESSION['username'])){echo ' '.$_SESSION['username'];} ?>!</h1>
            <p class="lead">This is your personalized content area.</p>
        </div>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<!-- login.php -->
<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
// Add your login logic here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Add your login form here -->
</body>
</html>
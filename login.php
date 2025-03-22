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
    <style>
        body {
            background: linear-gradient(to top, #1C2526, #4B5E6A, #6A0DAD);
            min-height: 100vh;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Add your login form here -->
</body>
</html>
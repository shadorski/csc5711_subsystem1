<!-- index.php -->
<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #136a8a, #267871);
            min-height: 100vh;
            color: white;
        }
        .navbar {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .content-card {
            background-color: rgba(255, 255, 255, 0.30);
            border: none;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="#">Subsystem 1</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" 
                                class="bi bi-person-circle" viewBox="0 0 16 16">
                                <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                                <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                            </svg>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <li><a class="dropdown-item" href="#">Profile</a></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="login.php">Sign In</a></li>
                                <li><a class="dropdown-item" href="signup.php">Sign Up</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="text-center">
                <h1 class="display-4">Content Goes Here</h1>
                <p class="lead">Welcome back! This is your personalized content area.</p>
            </div>
        <?php else: ?>
            <div class="card content-card p-5" style="max-width: 1000px; width: 100%;">
                <div class="card-body text-center">
                    <h2 class="card-title mb-4">Welcome to Subsystem 1</h2>
                    <p class="card-text mb-4">Please sign in or create an account to continue!</p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="login.php" class="btn btn-primary btn-lg px-3">Sign In</a>
                        <a href="signup.php" class="btn btn-outline-light btn-lg px-3">Sign Up</a>
                    </div>
                </div>
        </br>
        </br>
        </br>
        </br>
                <div class="footer-bottom" style="position: absolute; bottom: 0; left: 0; right: 0; padding-bottom: 15px;">
                    <div class="d-flex justify-content-center gap-3">
                        <p class="">Mark Mando - 202410903</p>
                        <p class="">Kaoma Chishimba - 202410902</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
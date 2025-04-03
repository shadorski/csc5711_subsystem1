<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 56px);">
        <div class="card content-card p-5" style="max-width: 1000px; width: 100%;">
            <div class="card-body text-center">
                <h2 class="card-title mb-4">Welcome to Subsystem 1</h2>
                <p class="card-text mb-4">Please sign in or create an account to continue!</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="/subsystem1/signup" class="btn btn-primary btn-lg px-3">Sign Up</a>
                    <a href="/subsystem1/login" class="btn btn-outline-light btn-lg px-3">Sign In</a>
                </div>
            </div>
        </div>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 56px);">
        <div class="card content-card p-5 text-center" style="max-width: 500px; width: 100%;">
            <div class="card-body">
                <h1 class="display-4 mb-4">404</h1>
                <svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" fill="currentColor" class="bi bi-exclamation-triangle mb-4" viewBox="0 0 16 16">
                    <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.16.16 0 0 1-.054.06.12.12 0 0 1-.066.017H1.146a.12.12 0 0 1-.066-.017.16.16 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057zm1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>
                    <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
                </svg>
                <h2 class="card-title mb-4">Page Not Found</h2>
                <p class="card-text mb-4">Oops! It looks like you’ve wandered off the path.</p>
                <a href="/subsystem1/" class="btn btn-primary btn-lg px-4">Back to Home</a>
            </div>
        </div>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
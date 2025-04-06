<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Offline - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 56px);">
        <div class="card content-card p-5 text-center" style="max-width: 500px; width: 100%;">
            <div class="card-body">
                <h1 class="display-4 mb-4">Subsystem 1</h1>
                <svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" fill="#dc3545" class="bi bi-exclamation-octagon-fill mb-4" viewBox="0 0 16 16">
                    <path d="M11.46.146A.5.5 0 0 0 11.107 0H4.893a.5.5 0 0 0-.353.146L.146 4.54A.5.5 0 0 0 0 4.893v6.214a.5.5 0 0 0 .146.353l4.394 4.394a.5.5 0 0 0 .353.146h6.214a.5.5 0 0 0 .353-.146l4.394-4.394a.5.5 0 0 0 .146-.353V4.893a.5.5 0 0 0-.146-.353zM8 4c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995A.905.905 0 0 1 8 4m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                </svg>
                <h2 class="card-title mb-4">System Offline</h2>
                <p class="card-text mb-4">
                    <?php echo isset($message) ? htmlspecialchars($message) : "Sorry, the system is currently unavailable due to a database issue. Please try again later."; ?>
                </p>
                <a href="/subsystem1/" class="btn btn-primary btn-lg px-4">Retry</a>
            </div>
        </div>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
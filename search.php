<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: /subsystem1/login");
    exit;
}

logDebug("Search page loaded");
$query = $query ?? '';
$results = $results ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
    <style>
        .search-result { margin-bottom: 1rem; }
        .result-meta { font-size: 0.9rem; color: #6c757d; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container mt-4" style="min-height: calc(100vh - 56px);">
        <h1 class="mb-4">Search Results for "<?php echo htmlspecialchars($query); ?>"</h1>

        <!-- Search Form -->
        <form method="GET" action="/subsystem1/search" class="mb-4">
            <div class="input-group input-group-lg">
                <input type="text" name="q" class="form-control" placeholder="Search by title, author, or content..." value="<?php echo htmlspecialchars($query); ?>" aria-label="Search documents">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>

        <!-- Results -->
        <?php if (empty($results)): ?>
            <p>No documents found.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($results as $doc): ?>
                    <div class="list-group-item search-result">
                        <h5>
                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                <?php echo htmlspecialchars($doc['title']); ?>
                            </a>
                        </h5>
                        <p class="result-meta">
                            Author: <?php echo htmlspecialchars($doc['author']); ?> | 
                            Uploaded: <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="mt-4">
            <a href="/subsystem1/" class="btn btn-primary">Back to Dashboard</a>
        </p>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
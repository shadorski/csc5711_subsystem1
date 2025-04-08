<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: /subsystem1/login");
    exit;
}

// Fetch data from the controller (passed via $data)
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$latest_by_user = $data['latest_by_user'] ?? [];
$latest_by_others = $data['latest_by_others'] ?? [];
$total_by_user = $data['total_by_user'] ?? 0;
$file_type_counts = $data['file_type_counts'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Subsystem 1</title>
    <link href="/subsystem1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/subsystem1/css/styles.css" rel="stylesheet">
    <style>
        .dashboard-card { min-height: 200px; }
        .list-group-item { border: none; }
        .search-bar { padding: 1.5rem; }
        .action-card { padding: 1.5rem; }
        .action-btn { height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .svg-icon { width: 3rem; height: 3rem; margin-right: 1rem; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container mt-4" style="min-height: calc(100vh - 56px);">
        <div class="text-center mb-4">
            <h1 class="display-4">Welcome Back, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="lead">This is your personalized content area.</p>
        </div>

        <div class="row">
            <!-- Left Side: 6 Columns -->
            <div class="col-md-6">
                <!-- Search Bar (6 Columns) -->
                <div class="card mb-4 search-bar">
                    <div class="card-body">
                        <h5 class="card-title">Search Documents</h5>
                        <form method="GET" action="/subsystem1/search">
                            <div class="input-group input-group-lg">
                                <input type="text" name="q" class="form-control" placeholder="Search by title..." aria-label="Search documents">
                                <button class="btn btn-primary" type="submit">Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Actions Card (6 Columns) -->
                <div class="card action-card">
                    <div class="card-body">
                        <h5 class="card-title">Actions</h5>
                        <div class="row">
                            <div class="col-12">
                                <a href="/subsystem1/upload" class="btn btn-primary action-btn">
                                    <svg class="svg-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Upload Document
                                </a>
                            </div>
                            <!-- Add more buttons here later, e.g., col-12 for full width -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: 6 Columns -->
            <div class="col-md-6">
                <div class="row">
                    <!-- Latest Uploads by User (3 Columns) -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title">Your Latest Uploads</h5>
                                <ul class="list-group">
                                    <?php if (empty($latest_by_user)): ?>
                                        <li class="list-group-item text-muted">No uploads yet.</li>
                                    <?php else: ?>
                                        <?php foreach ($latest_by_user as $doc): ?>
                                            <li class="list-group-item">
                                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                </a>
                                                <small class="text-muted d-block"><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Latest Uploads by Others (3 Columns) -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title">Uploads by Others</h5>
                                <ul class="list-group">
                                    <?php if (empty($latest_by_others)): ?>
                                        <li class="list-group-item text-muted">No uploads by others yet.</li>
                                    <?php else: ?>
                                        <?php foreach ($latest_by_others as $doc): ?>
                                            <li class="list-group-item">
                                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                </a>
                                                <small class="text-muted d-block"><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Total Documents by User (3 Columns) -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Your Total Documents</h5>
                                <p class="display-4"><?php echo $total_by_user; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- File Type Breakdown (3 Columns) -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title">Docs by File Type</h5>
                                <ul class="list-group">
                                    <?php if (empty($file_type_counts)): ?>
                                        <li class="list-group-item text-muted">No data available.</li>
                                    <?php else: ?>
                                        <?php foreach ($file_type_counts as $type => $count): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars(strtoupper($type)); ?>
                                                <span class="badge bg-primary rounded-pill"><?php echo $count; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/subsystem1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
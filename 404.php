<?php
require __DIR__ . '/includes/init.php';
http_response_code(404);
$pageTitle = 'Not Found';
require APP_ROOT . '/includes/header.php';
?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <h1 class="display-5">404</h1>
        <p class="muted">The page you requested could not be found.</p>
        <a class="btn btn-primary" href="<?= e(url('dashboard.php')) ?>">Back to Dashboard</a>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>

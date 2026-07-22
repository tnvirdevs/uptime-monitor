<?php
require __DIR__ . '/includes/init.php';

$pageTitle = 'Public Status';
$publicPage = true;
$monitors = Database::fetchAll('SELECT * FROM monitors WHERE status != "paused" ORDER BY monitor_name');
$overall = 'online';
foreach ($monitors as $monitor) {
    if ($monitor['status'] === 'offline') {
        $overall = 'offline';
        break;
    }
}

require APP_ROOT . '/includes/header.php';
?>
<div class="card shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 mb-1"><?= e(app_setting('site_name', 'Uptime Monitor')) ?> Status</h2>
            <div class="muted">Last updated <?= e(date('M j, Y H:i')) ?></div>
        </div>
        <span class="badge-status text-bg-<?= $overall === 'online' ? 'success' : 'danger' ?>"><?= $overall === 'online' ? 'All Systems Operational' : 'Service Disruption' ?></span>
    </div>
</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Status</th>
                    <?php if (config('public_status_show_targets', false)): ?><th>Target</th><?php endif; ?>
                    <th>SSL Expiry</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monitors as $monitor): ?>
                <tr>
                    <td><?= e($monitor['monitor_name']) ?></td>
                    <td><span class="status-dot <?= e($monitor['status']) ?>"></span><?= e(ucfirst($monitor['status'])) ?></td>
                    <?php if (config('public_status_show_targets', false)): ?><td><?= e(redact_target((string) $monitor['target'])) ?></td><?php endif; ?>
                    <td><?= $monitor['ssl_expires_at'] ? e(date('M j, Y', strtotime($monitor['ssl_expires_at']))) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>

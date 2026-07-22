<?php
require __DIR__ . '/includes/init.php';
require_login();

$pageTitle = 'Reports';
$period = (string) ($_GET['period'] ?? 'daily');
$days = ['daily' => 1, 'weekly' => 7, 'monthly' => 30][$period] ?? 1;
$intervalSql = (string) (int) $days;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="monitor-history.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['monitor', 'target', 'status', 'response_time', 'http_code', 'message', 'checked_at']);
    $rows = Database::fetchAll(
        "SELECT m.monitor_name, m.target, r.status, r.response_time, r.http_code, r.message, r.checked_at
         FROM monitor_results r JOIN monitors m ON m.id = r.monitor_id
         WHERE r.checked_at >= DATE_SUB(NOW(), INTERVAL {$intervalSql} DAY) ORDER BY r.checked_at DESC"
    );
    foreach ($rows as $row) {
        $row['target'] = redact_target((string) $row['target']);
        fputcsv($out, array_map('csv_safe', $row));
    }
    exit;
}

$summary = Database::fetch(
    "SELECT ROUND((SUM(status = 'online') / COUNT(*)) * 100, 2) AS uptime,
            ROUND(AVG(response_time), 0) AS avg_response,
            SUM(status = 'offline') AS failures
     FROM monitor_results WHERE checked_at >= DATE_SUB(NOW(), INTERVAL {$intervalSql} DAY)"
) ?: ['uptime' => 0, 'avg_response' => 0, 'failures' => 0];
$downtime = (int) (Database::fetch("SELECT COALESCE(SUM(duration), 0) AS downtime FROM incidents WHERE started_at >= DATE_SUB(NOW(), INTERVAL {$intervalSql} DAY)")['downtime'] ?? 0);
$recovery = (int) (Database::fetch("SELECT AVG(duration) AS recovery FROM incidents WHERE resolved_at IS NOT NULL AND started_at >= DATE_SUB(NOW(), INTERVAL {$intervalSql} DAY)")['recovery'] ?? 0);
$byMonitor = Database::fetchAll(
    "SELECT m.monitor_name,
            ROUND((SUM(r.status = 'online') / COUNT(*)) * 100, 2) AS uptime,
            ROUND(AVG(r.response_time), 0) AS response_time,
            SUM(r.status = 'offline') AS failures
     FROM monitor_results r JOIN monitors m ON m.id = r.monitor_id
     WHERE r.checked_at >= DATE_SUB(NOW(), INTERVAL {$intervalSql} DAY)
     GROUP BY m.id, m.monitor_name ORDER BY uptime ASC, failures DESC"
);
$series = Database::fetchAll(
    "SELECT DATE_FORMAT(checked_at, '%b %e') AS label,
            ROUND((SUM(status = 'online') / COUNT(*)) * 100, 2) AS uptime,
            ROUND(AVG(response_time), 0) AS response_time,
            SUM(status = 'offline') AS failures
     FROM monitor_results WHERE checked_at >= DATE_SUB(NOW(), INTERVAL {$intervalSql} DAY)
     GROUP BY DATE(checked_at) ORDER BY DATE(checked_at)"
);

require APP_ROOT . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="get" class="d-flex gap-2">
        <select class="form-select" name="period" onchange="this.form.submit()">
            <option value="daily" <?= selected($period, 'daily') ?>>Daily</option>
            <option value="weekly" <?= selected($period, 'weekly') ?>>Weekly</option>
            <option value="monthly" <?= selected($period, 'monthly') ?>>Monthly</option>
        </select>
    </form>
    <a class="btn btn-outline-primary" href="?period=<?= e($period) ?>&export=csv">Export CSV</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card metric"><div class="card-body"><div class="muted">Uptime</div><div class="value"><?= e((string) ($summary['uptime'] ?? 0)) ?>%</div></div></div></div>
    <div class="col-md-3"><div class="card metric"><div class="card-body"><div class="muted">Avg Response</div><div class="value"><?= e((string) ($summary['avg_response'] ?? 0)) ?> ms</div></div></div></div>
    <div class="col-md-3"><div class="card metric"><div class="card-body"><div class="muted">Downtime</div><div class="value"><?= e(moneyless_duration($downtime)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card metric"><div class="card-body"><div class="muted">Recovery Time</div><div class="value"><?= e(moneyless_duration($recovery)) ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-lg-4"><div class="card"><div class="card-header">Availability</div><div class="card-body chart-box"><canvas id="availability"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header">Response Time</div><div class="card-body chart-box"><canvas id="response"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header">Downtime Events</div><div class="card-body chart-box"><canvas id="downtime"></canvas></div></div></div>
</div>
<div class="card shadow-sm">
    <div class="card-header">Monitor Breakdown</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Monitor</th><th>Uptime</th><th>Avg Response</th><th>Failures</th></tr></thead>
            <tbody>
            <?php foreach ($byMonitor as $row): ?>
                <tr><td><?= e($row['monitor_name']) ?></td><td><?= e((string) $row['uptime']) ?>%</td><td><?= e((string) $row['response_time']) ?> ms</td><td><?= e((string) $row['failures']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = <?= json_for_script(array_column($series, 'label')) ?>;
    renderLineChart('availability', labels, [{ label: 'Uptime %', data: <?= json_for_script(array_map('floatval', array_column($series, 'uptime'))) ?>, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', fill: true, tension: .35 }]);
    renderLineChart('response', labels, [{ label: 'Response ms', data: <?= json_for_script(array_map('intval', array_column($series, 'response_time'))) ?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', fill: true, tension: .35 }]);
    renderBarChart('downtime', labels, [{ label: 'Failures', data: <?= json_for_script(array_map('intval', array_column($series, 'failures'))) ?>, backgroundColor: '#dc3545' }]);
});
</script>
<?php require APP_ROOT . '/includes/footer.php'; ?>

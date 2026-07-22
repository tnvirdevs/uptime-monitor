<?php
require __DIR__ . '/includes/init.php';
require_login();

$pageTitle = 'Dashboard';
$total = (int) (Database::fetch('SELECT COUNT(*) AS count FROM monitors')['count'] ?? 0);
$online = (int) (Database::fetch('SELECT COUNT(*) AS count FROM monitors WHERE status = "online"')['count'] ?? 0);
$offline = (int) (Database::fetch('SELECT COUNT(*) AS count FROM monitors WHERE status = "offline"')['count'] ?? 0);
$incidentsToday = (int) (Database::fetch('SELECT COUNT(*) AS count FROM incidents WHERE DATE(started_at) = CURDATE()')['count'] ?? 0);
$avgResponse = (int) (Database::fetch('SELECT AVG(response_time) AS avg_time FROM monitor_results WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')['avg_time'] ?? 0);
$availability = (float) (Database::fetch('SELECT (SUM(status = "online") / COUNT(*)) * 100 AS uptime FROM monitor_results WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['uptime'] ?? 100);
$sslSoon = (int) (Database::fetch('SELECT COUNT(*) AS count FROM monitors WHERE ssl_expires_at IS NOT NULL AND ssl_expires_at <= DATE_ADD(NOW(), INTERVAL 14 DAY)')['count'] ?? 0);
$latestChecks = Database::fetchAll(
    'SELECT r.*, m.monitor_name, m.target FROM monitor_results r JOIN monitors m ON m.id = r.monitor_id ORDER BY r.checked_at DESC LIMIT 10'
);
$latestIncidents = Database::fetchAll(
    'SELECT i.*, m.monitor_name FROM incidents i JOIN monitors m ON m.id = i.monitor_id ORDER BY i.started_at DESC LIMIT 8'
);

$hourly = Database::fetchAll(
    'SELECT DATE_FORMAT(checked_at, "%H:00") AS label, ROUND((SUM(status = "online") / COUNT(*)) * 100, 2) AS uptime
     FROM monitor_results WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
     GROUP BY DATE_FORMAT(checked_at, "%Y-%m-%d %H") ORDER BY MIN(checked_at)'
);
$weeklyResponse = Database::fetchAll(
    'SELECT DATE_FORMAT(checked_at, "%a") AS label, ROUND(AVG(response_time), 0) AS response_time
     FROM monitor_results WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(checked_at) ORDER BY DATE(checked_at)'
);
$monthlyAvailability = Database::fetchAll(
    'SELECT DATE_FORMAT(checked_at, "%b %e") AS label, ROUND((SUM(status = "online") / COUNT(*)) * 100, 2) AS uptime
     FROM monitor_results WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(checked_at) ORDER BY DATE(checked_at)'
);
$incidentHistory = Database::fetchAll(
    'SELECT DATE_FORMAT(started_at, "%b %e") AS label, COUNT(*) AS total
     FROM incidents WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(started_at) ORDER BY DATE(started_at)'
);

require APP_ROOT . '/includes/header.php';
?>
<div class="row g-3 mb-4">
    <?php
    $metrics = [
        ['Total Monitors', $total, 'primary'],
        ['Online', $online, 'success'],
        ['Offline', $offline, 'danger'],
        ['Incidents Today', $incidentsToday, 'warning'],
        ['Uptime', number_format($availability, 2) . '%', 'info'],
        ['Avg Response', $avgResponse . ' ms', 'secondary'],
        ['SSL Expiring Soon', $sslSoon, 'warning'],
    ];
    ?>
    <?php foreach ($metrics as [$label, $value, $color]): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card metric shadow-sm">
                <div class="card-body">
                    <div class="muted"><?= e($label) ?></div>
                    <div class="value text-<?= e($color) ?>"><?= e((string) $value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">24 Hour Uptime</div>
            <div class="card-body chart-box"><canvas id="uptime24"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Weekly Response Time</div>
            <div class="card-body chart-box"><canvas id="weeklyResponse"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Monthly Availability</div>
            <div class="card-body chart-box"><canvas id="monthlyAvailability"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Incident History</div>
            <div class="card-body chart-box"><canvas id="incidentHistory"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card shadow-sm">
            <div class="card-header">Latest Checks</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Monitor</th><th>Status</th><th>HTTP</th><th>Response</th><th>Checked</th></tr></thead>
                    <tbody>
                    <?php foreach ($latestChecks as $check): ?>
                        <tr>
                            <td><strong><?= e($check['monitor_name']) ?></strong><div class="muted small"><?= e(redact_target((string) $check['target'])) ?></div></td>
                            <td><span class="status-dot <?= e($check['status']) ?>"></span><?= e(ucfirst($check['status'])) ?></td>
                            <td><?= e((string) ($check['http_code'] ?? '-')) ?></td>
                            <td><?= e((string) $check['response_time']) ?> ms</td>
                            <td><?= e(format_dt($check['checked_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card shadow-sm">
            <div class="card-header">Latest Incidents</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Monitor</th><th>Started</th><th>Duration</th></tr></thead>
                    <tbody>
                    <?php foreach ($latestIncidents as $incident): ?>
                        <tr>
                            <td><?= e($incident['monitor_name']) ?><div class="muted small"><?= e($incident['reason']) ?></div></td>
                            <td><?= e(format_dt($incident['started_at'])) ?></td>
                            <td><?= $incident['resolved_at'] ? e(moneyless_duration((int) $incident['duration'])) : '<span class="badge text-bg-danger">Open</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    renderLineChart('uptime24', <?= json_for_script(array_column($hourly, 'label')) ?>, [{ label: 'Uptime %', data: <?= json_for_script(array_map('floatval', array_column($hourly, 'uptime'))) ?>, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', tension: .35, fill: true }]);
    renderLineChart('weeklyResponse', <?= json_for_script(array_column($weeklyResponse, 'label')) ?>, [{ label: 'Response ms', data: <?= json_for_script(array_map('intval', array_column($weeklyResponse, 'response_time'))) ?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', tension: .35, fill: true }]);
    renderLineChart('monthlyAvailability', <?= json_for_script(array_column($monthlyAvailability, 'label')) ?>, [{ label: 'Availability %', data: <?= json_for_script(array_map('floatval', array_column($monthlyAvailability, 'uptime'))) ?>, borderColor: '#20c997', backgroundColor: 'rgba(32,201,151,.12)', tension: .35, fill: true }]);
    renderBarChart('incidentHistory', <?= json_for_script(array_column($incidentHistory, 'label')) ?>, [{ label: 'Incidents', data: <?= json_for_script(array_map('intval', array_column($incidentHistory, 'total'))) ?>, backgroundColor: '#dc3545' }]);
});
</script>
<?php require APP_ROOT . '/includes/footer.php'; ?>

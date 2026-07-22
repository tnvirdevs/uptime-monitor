<?php
require __DIR__ . '/includes/init.php';
require_login();

$pageTitle = 'Incidents';
$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$date = (string) ($_GET['date'] ?? '');
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(m.monitor_name LIKE :search OR m.target LIKE :search OR i.reason LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($status === 'open') {
    $where[] = 'i.resolved_at IS NULL';
} elseif ($status === 'resolved') {
    $where[] = 'i.resolved_at IS NOT NULL';
}
if ($date !== '') {
    $where[] = 'DATE(i.started_at) = :date';
    $params['date'] = $date;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int) (Database::fetch("SELECT COUNT(*) AS count FROM incidents i JOIN monitors m ON m.id = i.monitor_id {$whereSql}", $params)['count'] ?? 0);
$paginator = new Paginator($total, (int) ($_GET['page'] ?? 1), 15);
$incidents = Database::fetchAll(
    "SELECT i.*, m.monitor_name, m.target FROM incidents i JOIN monitors m ON m.id = i.monitor_id
     {$whereSql} ORDER BY i.started_at DESC LIMIT {$paginator->perPage} OFFSET {$paginator->offset()}",
    $params
);

require APP_ROOT . '/includes/header.php';
?>
<div class="card shadow-sm">
    <div class="card-header">
        <form class="row g-2" method="get">
            <div class="col-md-5"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search incidents"></div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="open" <?= selected($status, 'open') ?>>Open</option>
                    <option value="resolved" <?= selected($status, 'resolved') ?>>Resolved</option>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="date" name="date" value="<?= e($date) ?>"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Monitor</th><th>Started</th><th>Resolved</th><th>Duration</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($incidents as $incident): ?>
                <tr>
                    <td><strong><?= e($incident['monitor_name']) ?></strong><div class="muted small"><?= e(redact_target((string) $incident['target'])) ?></div></td>
                    <td><?= e(format_dt($incident['started_at'])) ?></td>
                    <td><?= e(format_dt($incident['resolved_at'])) ?></td>
                    <td><?= $incident['resolved_at'] ? e(moneyless_duration((int) $incident['duration'])) : '-' ?></td>
                    <td><?= e($incident['reason']) ?></td>
                    <td><?= $incident['resolved_at'] ? '<span class="badge text-bg-success">Resolved</span>' : '<span class="badge text-bg-danger">Open</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?php require APP_ROOT . '/includes/pagination.php'; ?></div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>

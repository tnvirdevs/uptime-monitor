<?php
require __DIR__ . '/includes/init.php';
require_login();

$pageTitle = 'Monitors';
$types = ['website' => 'Website (HTTP)', 'https' => 'HTTPS Website', 'api' => 'API Endpoint', 'database' => 'Database', 'tcp' => 'TCP Port', 'ping' => 'Ping', 'ssl' => 'SSL Certificate'];
$intervals = [1 => 'Every 1 Minute', 5 => 'Every 5 Minutes', 10 => 'Every 10 Minutes', 15 => 'Every 15 Minutes', 30 => 'Every 30 Minutes', 60 => 'Every Hour'];
$groups = Database::fetchAll('SELECT id, group_name FROM notification_groups ORDER BY group_name');
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        Auth::requireRole('operator');
        Database::query('DELETE FROM monitors WHERE id = :id', ['id' => (int) $_POST['id']]);
        flash('success', 'Monitor deleted.');
        redirect('monitors.php');
    }

    if ($action === 'check') {
        Auth::requireRole('operator');
        $checker = new MonitorChecker();
        $result = $checker->checkNow((int) $_POST['id']);
        flash($result['status'] === 'online' ? 'success' : 'danger', $result['monitor_name'] . ': ' . $result['message']);
        redirect('monitors.php');
    }

    if ($action === 'save') {
        Auth::requireRole('operator');
        $data = [
            'monitor_name' => trim((string) $_POST['monitor_name']),
            'monitor_type' => (string) $_POST['monitor_type'],
            'target' => trim((string) $_POST['target']),
            'port' => $_POST['port'] !== '' ? (int) $_POST['port'] : null,
            'check_interval' => (int) $_POST['check_interval'],
            'timeout' => (int) $_POST['timeout'],
            'expected_status_code' => (int) ($_POST['expected_status_code'] ?: 200),
            'keyword' => trim((string) ($_POST['keyword'] ?? '')),
            'ssl_monitor' => isset($_POST['ssl_monitor']) ? 1 : 0,
            'notification_group' => $_POST['notification_group'] !== '' ? (int) $_POST['notification_group'] : null,
            'status' => (string) $_POST['status'],
            'retry_attempts' => max(1, min(5, (int) ($_POST['retry_attempts'] ?? 3))),
            'notification_cooldown' => max(5, min(1440, (int) ($_POST['notification_cooldown'] ?? 30))),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
        ];

        $errors = Validator::required($data, ['monitor_name', 'monitor_type', 'target', 'check_interval', 'timeout']);
        if (!array_key_exists($data['monitor_type'], $types)) {
            $errors['monitor_type'] = 'Invalid monitor type.';
        }
        if (!Validator::intRange($data['timeout'], 1, 120)) {
            $errors['timeout'] = 'Timeout must be between 1 and 120 seconds.';
        }
        if (!array_key_exists($data['check_interval'], $intervals)) {
            $errors['check_interval'] = 'Invalid check interval.';
        }
        try {
            Security::assertMonitorTargetAllowed($data['target'], $data['port']);
        } catch (RuntimeException $exception) {
            $errors['target'] = $exception->getMessage() . ' Set allow_private_monitor_targets to true only if you intentionally monitor internal assets.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            $editing = $data + ['id' => (int) ($_POST['id'] ?? 0)];
        } elseif (!empty($_POST['id'])) {
            $data['id'] = (int) $_POST['id'];
            Database::query(
                'UPDATE monitors SET monitor_name=:monitor_name, monitor_type=:monitor_type, target=:target, port=:port,
                 check_interval=:check_interval, timeout=:timeout, expected_status_code=:expected_status_code, keyword=:keyword,
                 ssl_monitor=:ssl_monitor, notification_group=:notification_group, status=:status, retry_attempts=:retry_attempts,
                 notification_cooldown=:notification_cooldown, maintenance_mode=:maintenance_mode WHERE id=:id',
                $data
            );
            flash('success', 'Monitor updated.');
            redirect('monitors.php');
        } else {
            Database::query(
                'INSERT INTO monitors (monitor_name, monitor_type, target, port, check_interval, timeout, expected_status_code,
                 keyword, ssl_monitor, notification_group, status, retry_attempts, notification_cooldown, maintenance_mode, created_at)
                 VALUES (:monitor_name, :monitor_type, :target, :port, :check_interval, :timeout, :expected_status_code,
                 :keyword, :ssl_monitor, :notification_group, :status, :retry_attempts, :notification_cooldown, :maintenance_mode, NOW())',
                $data
            );
            flash('success', 'Monitor created.');
            redirect('monitors.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = Database::fetch('SELECT * FROM monitors WHERE id = :id', ['id' => (int) $_GET['edit']]);
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$group = (string) ($_GET['group'] ?? '');
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(m.monitor_name LIKE :search OR m.target LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = 'm.status = :status';
    $params['status'] = $status;
}
if ($type !== '') {
    $where[] = 'm.monitor_type = :type';
    $params['type'] = $type;
}
if ($group !== '') {
    $where[] = 'm.notification_group = :group_id';
    $params['group_id'] = (int) $group;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int) (Database::fetch("SELECT COUNT(*) AS count FROM monitors m {$whereSql}", $params)['count'] ?? 0);
$paginator = new Paginator($total, (int) ($_GET['page'] ?? 1), 10);
$monitors = Database::fetchAll(
    "SELECT m.*, g.group_name FROM monitors m LEFT JOIN notification_groups g ON g.id = m.notification_group
     {$whereSql} ORDER BY m.created_at DESC LIMIT {$paginator->perPage} OFFSET {$paginator->offset()}",
    $params
);

$defaults = [
    'id' => '',
    'monitor_name' => '',
    'monitor_type' => 'website',
    'target' => '',
    'port' => '',
    'check_interval' => (int) app_setting('default_check_interval', 5),
    'timeout' => (int) app_setting('default_timeout', 10),
    'expected_status_code' => 200,
    'keyword' => '',
    'ssl_monitor' => 0,
    'notification_group' => '',
    'status' => 'online',
    'retry_attempts' => 3,
    'notification_cooldown' => 30,
    'maintenance_mode' => 0,
];
$form = ($editing ?: []) + $defaults;

require APP_ROOT . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card shadow-sm">
            <div class="card-header"><?= $editing ? 'Edit Monitor' : 'Create Monitor' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
                    <div class="mb-3">
                        <label class="form-label">Monitor Name</label>
                        <input class="form-control" name="monitor_name" value="<?= e((string) $form['monitor_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monitor Type</label>
                        <select class="form-select" name="monitor_type" required>
                            <?php foreach ($types as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= selected($form['monitor_type'], $value) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL / Host / DSN</label>
                        <input class="form-control" name="target" value="<?= e((string) $form['target']) ?>" required>
                        <div class="form-text">Database format: mysql://user:pass@host:3306/database</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label">Port</label>
                            <input class="form-control" type="number" name="port" value="<?= e((string) $form['port']) ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Timeout</label>
                            <input class="form-control" type="number" name="timeout" min="1" max="120" value="<?= e((string) $form['timeout']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Check Interval</label>
                        <select class="form-select" name="check_interval">
                            <?php foreach ($intervals as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>" <?= selected($form['check_interval'], $value) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label">Expected HTTP Code</label>
                            <input class="form-control" type="number" name="expected_status_code" value="<?= e((string) $form['expected_status_code']) ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Retries</label>
                            <input class="form-control" type="number" name="retry_attempts" min="1" max="5" value="<?= e((string) $form['retry_attempts']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keyword</label>
                        <input class="form-control" name="keyword" value="<?= e((string) $form['keyword']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notification Group</label>
                        <select class="form-select" name="notification_group">
                            <option value="">Use admins/operators</option>
                            <?php foreach ($groups as $item): ?>
                                <option value="<?= e((string) $item['id']) ?>" <?= selected($form['notification_group'], $item['id']) ?>><?= e($item['group_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="online" <?= selected($form['status'], 'online') ?>>Active</option>
                                <option value="paused" <?= selected($form['status'], 'paused') ?>>Paused</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Cooldown Minutes</label>
                            <input class="form-control" type="number" name="notification_cooldown" value="<?= e((string) $form['notification_cooldown']) ?>">
                        </div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="ssl_monitor" id="ssl_monitor" <?= checked($form['ssl_monitor']) ?>>
                        <label class="form-check-label" for="ssl_monitor">Enable SSL Monitoring</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" <?= checked($form['maintenance_mode']) ?>>
                        <label class="form-check-label" for="maintenance_mode">Maintenance Mode</label>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Update Monitor' : 'Create Monitor' ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(url('monitors.php')) ?>">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <form class="row g-2 align-items-center" method="get">
                    <div class="col-md-4"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search monitor, URL, host"></div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">Status</option>
                            <option value="online" <?= selected($status, 'online') ?>>Online</option>
                            <option value="offline" <?= selected($status, 'offline') ?>>Offline</option>
                            <option value="paused" <?= selected($status, 'paused') ?>>Paused</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="type">
                            <option value="">Type</option>
                            <?php foreach ($types as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= selected($type, $value) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="group">
                            <option value="">Group</option>
                            <?php foreach ($groups as $item): ?>
                                <option value="<?= e((string) $item['id']) ?>" <?= selected($group, $item['id']) ?>><?= e($item['group_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Interval</th><th>SSL</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($monitors as $monitor): ?>
                        <tr>
                            <td><strong><?= e($monitor['monitor_name']) ?></strong><div class="muted small"><?= e(redact_target((string) $monitor['target'])) ?></div></td>
                            <td><?= e($types[$monitor['monitor_type']] ?? $monitor['monitor_type']) ?></td>
                            <td><span class="status-dot <?= e($monitor['status']) ?>"></span><?= e(ucfirst($monitor['status'])) ?></td>
                            <td><?= e((string) $monitor['check_interval']) ?> min</td>
                            <td><?= $monitor['ssl_expires_at'] ? e(date('M j, Y', strtotime($monitor['ssl_expires_at']))) : '-' ?></td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <form method="post">
                                        <?= Csrf::field() ?><input type="hidden" name="action" value="check"><input type="hidden" name="id" value="<?= e((string) $monitor['id']) ?>">
                                        <button class="btn btn-sm btn-outline-success" type="submit">Check Now</button>
                                    </form>
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= e((string) $monitor['id']) ?>">Edit</a>
                                    <form method="post">
                                        <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string) $monitor['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this monitor and its history?" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer"><?php require APP_ROOT . '/includes/pagination.php'; ?></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>

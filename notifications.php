<?php
require __DIR__ . '/includes/init.php';
require_login();
Auth::requireRole('operator');

$pageTitle = 'Notifications';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        Database::query('DELETE FROM notification_groups WHERE id = :id', ['id' => (int) $_POST['id']]);
        flash('success', 'Notification group deleted.');
        redirect('notifications.php');
    }

    if ($action === 'save') {
        $data = [
            'group_name' => trim((string) $_POST['group_name']),
            'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
            'telegram_enabled' => isset($_POST['telegram_enabled']) ? 1 : 0,
            'email_recipients' => trim((string) ($_POST['email_recipients'] ?? '')),
        ];

        $errors = Validator::required($data, ['group_name']);
        $emails = array_filter(array_map('trim', explode(',', $data['email_recipients'])));
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email_recipients'] = 'Notification recipients must be comma-separated valid email addresses.';
                break;
            }
        }
        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            $editing = $data + ['id' => (int) ($_POST['id'] ?? 0)];
        } elseif (!empty($_POST['id'])) {
            $data['id'] = (int) $_POST['id'];
            Database::query(
                'UPDATE notification_groups SET group_name=:group_name, email_enabled=:email_enabled, telegram_enabled=:telegram_enabled, email_recipients=:email_recipients WHERE id=:id',
                $data
            );
            flash('success', 'Notification group updated.');
            redirect('notifications.php');
        } else {
            Database::query(
                'INSERT INTO notification_groups (group_name, email_enabled, telegram_enabled, email_recipients) VALUES (:group_name, :email_enabled, :telegram_enabled, :email_recipients)',
                $data
            );
            flash('success', 'Notification group created.');
            redirect('notifications.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = Database::fetch('SELECT * FROM notification_groups WHERE id = :id', ['id' => (int) $_GET['edit']]);
}

$groups = Database::fetchAll('SELECT * FROM notification_groups ORDER BY group_name');
$form = ($editing ?: []) + ['id' => '', 'group_name' => '', 'email_enabled' => 1, 'telegram_enabled' => 0, 'email_recipients' => ''];

require APP_ROOT . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header"><?= $editing ? 'Edit Group' : 'Create Group' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
                    <div class="mb-3">
                        <label class="form-label">Group Name</label>
                        <input class="form-control" name="group_name" value="<?= e((string) $form['group_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Recipients</label>
                        <textarea class="form-control" name="email_recipients" rows="3" placeholder="ops@example.com, admin@example.com"><?= e((string) $form['email_recipients']) ?></textarea>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="email_enabled" id="email_enabled" <?= checked($form['email_enabled']) ?>>
                        <label class="form-check-label" for="email_enabled">Email Enabled</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="telegram_enabled" id="telegram_enabled" <?= checked($form['telegram_enabled']) ?>>
                        <label class="form-check-label" for="telegram_enabled">Telegram Enabled</label>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Group</button>
                    <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(url('notifications.php')) ?>">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">Notification Groups</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Name</th><th>Email</th><th>Telegram</th><th>Recipients</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><?= e($group['group_name']) ?></td>
                            <td><?= (int) $group['email_enabled'] ? 'Enabled' : 'Disabled' ?></td>
                            <td><?= (int) $group['telegram_enabled'] ? 'Enabled' : 'Disabled' ?></td>
                            <td><?= e($group['email_recipients'] ?: 'Admins and operators') ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= e((string) $group['id']) ?>">Edit</a>
                                <form class="d-inline" method="post">
                                    <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this notification group?" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>

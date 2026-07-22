<?php
require __DIR__ . '/includes/init.php';
Auth::requireRole('administrator');

$pageTitle = 'Users';
$editing = null;
$roles = ['administrator' => 'Administrator', 'operator' => 'Operator', 'viewer' => 'Viewer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id === (int) current_user()['id']) {
            flash('danger', 'You cannot delete your own account.');
        } else {
            Database::query('DELETE FROM users WHERE id = :id', ['id' => $id]);
            flash('success', 'User deleted.');
        }
        redirect('users.php');
    }

    if ($action === 'save') {
        $data = [
            'full_name' => trim((string) $_POST['full_name']),
            'username' => trim((string) $_POST['username']),
            'email' => trim((string) $_POST['email']),
            'role' => (string) $_POST['role'],
            'status' => (string) $_POST['status'],
        ];
        $password = (string) ($_POST['password'] ?? '');
        $errors = Validator::required($data, ['full_name', 'username', 'email', 'role', 'status']);
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required.';
        }
        if (!array_key_exists($data['role'], $roles)) {
            $errors['role'] = 'Invalid role.';
        }
        if ((!$id || $password !== '') && strlen($password) < 10) {
            $errors['password'] = 'Password must be at least 10 characters.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            $editing = $data + ['id' => $id];
        } elseif ($id) {
            $data['id'] = $id;
            Database::query('UPDATE users SET full_name=:full_name, username=:username, email=:email, role=:role, status=:status WHERE id=:id', $data);
            if ($password !== '') {
                Database::query('UPDATE users SET password = :password WHERE id = :id', ['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
            }
            flash('success', 'User updated.');
            redirect('users.php');
        } else {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            Database::query(
                'INSERT INTO users (full_name, username, email, password, role, status, created_at)
                 VALUES (:full_name, :username, :email, :password, :role, :status, NOW())',
                $data
            );
            flash('success', 'User created.');
            redirect('users.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = Database::fetch('SELECT id, full_name, username, email, role, status FROM users WHERE id = :id', ['id' => (int) $_GET['edit']]);
}

$users = Database::fetchAll('SELECT id, full_name, username, email, role, status, created_at FROM users ORDER BY created_at DESC');
$form = ($editing ?: []) + ['id' => '', 'full_name' => '', 'username' => '', 'email' => '', 'role' => 'viewer', 'status' => 'active'];

require APP_ROOT . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header"><?= $editing ? 'Edit User' : 'Create User' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
                    <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e((string) $form['full_name']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e((string) $form['username']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e((string) $form['email']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" <?= $editing ? '' : 'required' ?>><div class="form-text"><?= $editing ? 'Leave blank to keep current password.' : 'Minimum 10 characters.' ?></div></div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <?php foreach ($roles as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($form['role'], $value) ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= selected($form['status'], 'active') ?>>Active</option>
                            <option value="disabled" <?= selected($form['status'], 'disabled') ?>>Disabled</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Save User</button>
                    <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(url('users.php')) ?>">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">Team</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?= e($row['full_name']) ?></td><td><?= e($row['username']) ?></td><td><?= e($row['email']) ?></td><td><?= e(ucfirst($row['role'])) ?></td><td><?= e(ucfirst($row['status'])) ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= e((string) $row['id']) ?>">Edit</a>
                                <form class="d-inline" method="post">
                                    <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this user?" type="submit">Delete</button>
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

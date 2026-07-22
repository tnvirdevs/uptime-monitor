<?php
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['csrf_token'] ?? null);
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (Auth::tooManyAttempts($username)) {
        $error = 'Too many failed login attempts. Try again in 15 minutes.';
    } elseif (Auth::attempt($username, $password)) {
        redirect('dashboard.php');
    } else {
        $error = 'Invalid username or password.';
    }
}

$title = 'Login';
require APP_ROOT . '/includes/header.php';
?>
<div class="card auth-card shadow-sm">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <?php if (app_setting('application_logo')): ?>
                <img class="login-logo mb-3" src="<?= e((string) app_setting('application_logo')) ?>" alt="">
            <?php else: ?>
                <div class="brand-mark mx-auto mb-3">UM</div>
            <?php endif; ?>
            <h1 class="h4 mb-1"><?= e(app_setting('site_name', 'Uptime Monitor')) ?></h1>
            <p class="muted mb-0">Sign in to your monitoring dashboard</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Login</button>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>

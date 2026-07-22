<?php
require __DIR__ . '/includes/init.php';
Auth::requireRole('administrator');

$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['csrf_token'] ?? null);
    $timezone = trim((string) $_POST['timezone']);
    if (!in_array($timezone, timezone_identifiers_list(), true)) {
        flash('danger', 'Invalid timezone.');
        redirect('settings.php');
    }

    $smtpPort = (int) ($_POST['smtp_port'] ?: 587);
    if ($smtpPort < 1 || $smtpPort > 65535) {
        flash('danger', 'SMTP port must be between 1 and 65535.');
        redirect('settings.php');
    }

    $senderEmail = trim((string) $_POST['smtp_from_email']);
    if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Sender email is invalid.');
        redirect('settings.php');
    }

    $logo = trim((string) $_POST['application_logo']);
    if ($logo !== '' && !preg_match('#^(https?://|/)#i', $logo)) {
        flash('danger', 'Application logo must be an HTTPS/HTTP or root-relative URL.');
        redirect('settings.php');
    }

    $hasNewSecrets = trim((string) $_POST['smtp_password']) !== '' || trim((string) $_POST['telegram_bot_token']) !== '';
    if ($hasNewSecrets && (string) config('secret_key', '') === 'change-this-32-byte-secret-key') {
        flash('danger', 'Change secret_key in config/config.php before saving SMTP passwords or Telegram tokens.');
        redirect('settings.php');
    }

    Setting::update([
        'site_name' => trim((string) $_POST['site_name']),
        'timezone' => $timezone,
        'smtp_host' => trim((string) $_POST['smtp_host']),
        'smtp_port' => $smtpPort,
        'smtp_username' => trim((string) $_POST['smtp_username']),
        'smtp_password' => trim((string) $_POST['smtp_password']),
        'smtp_from_name' => trim((string) $_POST['smtp_from_name']),
        'smtp_from_email' => $senderEmail,
        'telegram_bot_token' => trim((string) $_POST['telegram_bot_token']),
        'telegram_chat_id' => trim((string) $_POST['telegram_chat_id']),
        'default_timeout' => max(1, (int) $_POST['default_timeout']),
        'default_check_interval' => max(1, (int) $_POST['default_check_interval']),
        'application_logo' => $logo,
    ]);
    flash('success', 'Settings updated.');
    redirect('settings.php');
}

$settings = Setting::all() + [
    'site_name' => 'Uptime Monitor',
    'timezone' => 'UTC',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from_name' => 'Uptime Monitor',
    'smtp_from_email' => '',
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',
    'default_timeout' => 10,
    'default_check_interval' => 5,
    'application_logo' => '',
];

require APP_ROOT . '/includes/header.php';
?>
<form method="post">
    <?= Csrf::field() ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">Application</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Site Name</label><input class="form-control" name="site_name" value="<?= e((string) $settings['site_name']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="<?= e((string) $settings['timezone']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Application Logo URL</label><input class="form-control" name="application_logo" value="<?= e((string) $settings['application_logo']) ?>"></div>
                    <div class="row g-2">
                        <div class="col-6 mb-3"><label class="form-label">Default Timeout</label><input class="form-control" type="number" name="default_timeout" value="<?= e((string) $settings['default_timeout']) ?>"></div>
                        <div class="col-6 mb-3"><label class="form-label">Default Interval</label><input class="form-control" type="number" name="default_check_interval" value="<?= e((string) $settings['default_check_interval']) ?>"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">SMTP Email</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">SMTP Host</label><input class="form-control" name="smtp_host" value="<?= e((string) $settings['smtp_host']) ?>"></div>
                    <div class="mb-3"><label class="form-label">SMTP Port</label><input class="form-control" type="number" name="smtp_port" value="<?= e((string) $settings['smtp_port']) ?>"></div>
                    <div class="mb-3"><label class="form-label">SMTP Username</label><input class="form-control" name="smtp_username" value="<?= e((string) $settings['smtp_username']) ?>"></div>
                    <div class="mb-3"><label class="form-label">SMTP Password</label><input class="form-control" type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= $settings['smtp_password'] ? 'Saved; leave blank to keep current password' : '' ?>"></div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3"><label class="form-label">Sender Name</label><input class="form-control" name="smtp_from_name" value="<?= e((string) $settings['smtp_from_name']) ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Sender Email</label><input class="form-control" type="email" name="smtp_from_email" value="<?= e((string) $settings['smtp_from_email']) ?>"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">Telegram</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Bot Token</label><input class="form-control" name="telegram_bot_token" autocomplete="off" placeholder="<?= $settings['telegram_bot_token'] ? 'Saved; leave blank to keep current token' : '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Chat ID</label><input class="form-control" name="telegram_chat_id" value="<?= e((string) $settings['telegram_chat_id']) ?>"></div>
                </div>
            </div>
        </div>
        <div class="col-12"><button class="btn btn-primary" type="submit">Save Settings</button></div>
    </div>
</form>
<?php require APP_ROOT . '/includes/footer.php'; ?>

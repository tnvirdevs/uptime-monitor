<?php
$current = basename($_SERVER['SCRIPT_NAME']);
$items = [
    'dashboard.php' => ['Dashboard', 'grid'],
    'monitors.php' => ['Monitors', 'activity'],
    'incidents.php' => ['Incidents', 'alert'],
    'notifications.php' => ['Notifications', 'bell'],
    'reports.php' => ['Reports', 'chart'],
    'status.php' => ['Status Page', 'globe'],
    'users.php' => ['Users', 'users'],
    'settings.php' => ['Settings', 'settings'],
];
?>
<aside class="sidebar">
    <a class="brand" href="<?= e(url('dashboard.php')) ?>">
        <?php if (app_setting('application_logo')): ?>
            <img class="brand-logo" src="<?= e((string) app_setting('application_logo')) ?>" alt="">
        <?php else: ?>
            <span class="brand-mark">UM</span>
        <?php endif; ?>
        <span><?= e(app_setting('site_name', 'Uptime Monitor')) ?></span>
    </a>
    <nav>
        <?php foreach ($items as $href => [$label, $icon]): ?>
            <?php if ($href === 'users.php' && !current_role_at_least('administrator')) continue; ?>
            <a class="<?= $current === $href ? 'active' : '' ?>" href="<?= e(url($href)) ?>" title="<?= e($label) ?>">
                <span class="nav-icon"><?= e(strtoupper(substr($icon, 0, 1))) ?></span>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <form class="logout" method="post" action="<?= e(url('logout.php')) ?>">
        <?= Csrf::field() ?>
        <button class="btn btn-link text-start p-0 text-decoration-none text-light" type="submit">Logout</button>
    </form>
</aside>

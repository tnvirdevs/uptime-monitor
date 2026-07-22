<?php
$settings = Setting::all();
$title = $title ?? ($settings['site_name'] ?? 'Uptime Monitor');
$user = current_user();
$isPublicPage = !empty($publicPage);
?>
<!doctype html>
<html lang="en" data-theme="<?= e($_COOKIE['theme'] ?? 'light') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <?php require APP_ROOT . '/includes/sidebar.php'; ?>
    <?php endif; ?>
    <main class="app-main <?= (!$user && !$isPublicPage) ? 'auth-main' : '' ?>">
        <?php if ($user): ?>
            <header class="topbar">
                <div>
                    <h1><?= e($pageTitle ?? $title) ?></h1>
                    <span><?= e(date('l, M j, Y H:i')) ?></span>
                </div>
                <div class="topbar-actions">
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="themeToggle" title="Toggle theme">Theme</button>
                    <span class="user-pill"><?= e($user['full_name']) ?></span>
                </div>
            </header>
        <?php endif; ?>

        <?php foreach (flashes() as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($message['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>

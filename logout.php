<?php
require __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

Csrf::verify($_POST['csrf_token'] ?? null);
Auth::logout();
redirect('login.php');

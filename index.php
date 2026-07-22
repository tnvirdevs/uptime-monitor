<?php
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('dashboard.php');
}

redirect('login.php');

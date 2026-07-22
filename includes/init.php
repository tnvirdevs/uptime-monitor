<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$appConfig = require APP_ROOT . '/config/config.php';
date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) ($appConfig['session_name'] ?? 'uptime_monitor_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (bool) ($appConfig['secure_cookies'] ?? false) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once APP_ROOT . '/includes/helpers.php';

spl_autoload_register(static function (string $class): void {
    $file = APP_ROOT . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

try {
    $configuredTimezone = Setting::get('timezone', $appConfig['timezone'] ?? 'UTC');
    if (is_string($configuredTimezone) && in_array($configuredTimezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($configuredTimezone);
    }
} catch (Throwable) {
    date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));
}

if ((bool) ($appConfig['debug'] ?? false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

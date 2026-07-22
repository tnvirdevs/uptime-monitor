<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/config.php';
    }

    return $config[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    $path = ltrim($path, '/');

    return $base . ($path !== '' ? '/' . $path : '');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function selected(mixed $left, mixed $right): string
{
    return (string) $left === (string) $right ? 'selected' : '';
}

function checked(mixed $value): string
{
    return (bool) $value ? 'checked' : '';
}

function moneyless_duration(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    }

    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}

function require_login(): void
{
    Auth::requireLogin();
}

function current_user(): ?array
{
    return Auth::user();
}

function current_role_at_least(string $role): bool
{
    return Auth::hasRole($role);
}

function app_setting(string $key, mixed $default = null): mixed
{
    return Setting::get($key, $default);
}

function format_dt(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('M j, Y H:i', strtotime($value));
}

function redact_target(?string $target): string
{
    return Security::redactTarget($target);
}

function json_for_script(mixed $value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

function csv_safe(mixed $value): string
{
    $value = (string) $value;
    if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
}

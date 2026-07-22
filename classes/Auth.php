<?php

declare(strict_types=1);

final class Auth
{
    private const ROLES = ['viewer' => 1, 'operator' => 2, 'administrator' => 3];
    private const DEFAULT_ADMIN_BOOTSTRAP_HASH = '$2y$10$llcw8Cbuww90KW1dYB6Rn.98iM0JyTiC1VBT1WveVKz99VqbhFLpG';

    public static function attempt(string $username, string $password): bool
    {
        if (self::tooManyAttempts($username)) {
            return false;
        }

        $user = Database::fetch(
            'SELECT * FROM users WHERE username = :username AND status = "active" LIMIT 1',
            ['username' => $username]
        );

        if (!$user) {
            self::recordFailure($username);
            return false;
        }

        $storedHash = (string) $user['password'];
        $isBootstrapHash = $storedHash === self::DEFAULT_ADMIN_BOOTSTRAP_HASH;
        $bootstrapDefault = $isBootstrapHash && $user['username'] === 'admin' && hash_equals('Admin@12345', $password);

        if ($isBootstrapHash && !$bootstrapDefault) {
            self::recordFailure($username);
            return false;
        }

        if (!$isBootstrapHash && !password_verify($password, $storedHash)) {
            self::recordFailure($username);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        self::clearFailures($username);

        if ($bootstrapDefault || password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password = :password WHERE id = :id', [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
        }

        return true;
    }

    public static function tooManyAttempts(string $username): bool
    {
        $ip = self::clientIp();
        $count = (int) (Database::fetch(
            'SELECT COUNT(*) AS count FROM login_attempts WHERE ip_address = :ip AND username = :username AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            ['ip' => $ip, 'username' => strtolower($username)]
        )['count'] ?? 0);

        return $count >= 8;
    }

    public static function recordFailure(string $username): void
    {
        Database::query('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        Database::query(
            'INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (:ip, :username, NOW())',
            ['ip' => self::clientIp(), 'username' => strtolower($username)]
        );
    }

    public static function clearFailures(string $username): void
    {
        Database::query(
            'DELETE FROM login_attempts WHERE ip_address = :ip AND username = :username',
            ['ip' => self::clientIp(), 'username' => strtolower($username)]
        );
    }

    private static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
    }

    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        static $user = null;
        if ($user === null) {
            $user = Database::fetch('SELECT id, full_name, username, email, role, status FROM users WHERE id = :id', [
                'id' => $_SESSION['user_id'],
            ]);
        }

        return $user;
    }

    public static function requireLogin(): void
    {
        if (!self::user()) {
            redirect('login.php');
        }

        if (self::user()['status'] !== 'active') {
            self::logout();
            redirect('login.php');
        }

        if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 7200) {
            self::logout();
            redirect('login.php');
        }

        $_SESSION['last_activity'] = time();
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if (!self::hasRole($role)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    public static function hasRole(string $role): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return (self::ROLES[strtolower((string) $user['role'])] ?? 0) >= (self::ROLES[strtolower($role)] ?? 99);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

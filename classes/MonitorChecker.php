<?php

declare(strict_types=1);

final class MonitorChecker
{
    private NotificationService $notifications;

    public function __construct(?NotificationService $notifications = null)
    {
        $this->notifications = $notifications ?? new NotificationService();
    }

    public function runDueChecks(): array
    {
        $monitors = Database::fetchAll(
            'SELECT * FROM monitors
             WHERE status != "paused"
               AND (last_checked_at IS NULL OR TIMESTAMPDIFF(SECOND, last_checked_at, NOW()) >= check_interval * 60)
             ORDER BY last_checked_at ASC, id ASC'
        );

        $results = [];
        foreach ($monitors as $monitor) {
            $results[] = $this->checkAndStore($monitor);
        }

        return $results;
    }

    public function checkNow(int $monitorId): array
    {
        $monitor = Database::fetch('SELECT * FROM monitors WHERE id = :id', ['id' => $monitorId]);
        if (!$monitor) {
            throw new RuntimeException('Monitor not found.');
        }

        return $this->checkAndStore($monitor);
    }

    public function checkAndStore(array $monitor): array
    {
        $result = null;
        $tries = max(1, (int) ($monitor['retry_attempts'] ?? 3));
        for ($attempt = 1; $attempt <= $tries; $attempt++) {
            $result = $this->executeCheck($monitor);
            if ($result['status'] === 'online') {
                break;
            }
            usleep(200000);
        }

        $result ??= ['status' => 'offline', 'response_time' => 0, 'http_code' => null, 'message' => 'No result'];
        $result['message'] = substr(redact_target((string) $result['message']), 0, 500);
        Database::query(
            'INSERT INTO monitor_results (monitor_id, status, response_time, http_code, message, checked_at)
             VALUES (:monitor_id, :status, :response_time, :http_code, :message, NOW())',
            [
                'monitor_id' => $monitor['id'],
                'status' => $result['status'],
                'response_time' => $result['response_time'],
                'http_code' => $result['http_code'],
                'message' => $result['message'],
            ]
        );

        $this->handleIncidentState($monitor, $result);

        Database::query(
            'UPDATE monitors SET status = :status, last_checked_at = NOW(), ssl_expires_at = :ssl_expires_at WHERE id = :id',
            [
                'status' => $result['status'],
                'ssl_expires_at' => $result['ssl_expires_at'] ?? $monitor['ssl_expires_at'] ?? null,
                'id' => $monitor['id'],
            ]
        );

        if (!empty($result['ssl_days_remaining']) && (int) $result['ssl_days_remaining'] <= 14) {
            $this->notifyWithCooldown($monitor, 'ssl', 'SSL certificate expires in ' . (int) $result['ssl_days_remaining'] . ' days.');
        }

        return $result + ['monitor_id' => $monitor['id'], 'monitor_name' => $monitor['monitor_name']];
    }

    private function handleIncidentState(array $monitor, array $result): void
    {
        $openIncident = Database::fetch(
            'SELECT * FROM incidents WHERE monitor_id = :monitor_id AND resolved_at IS NULL ORDER BY started_at DESC LIMIT 1',
            ['monitor_id' => $monitor['id']]
        );

        if ($result['status'] === 'offline' && !$openIncident && (int) ($monitor['maintenance_mode'] ?? 0) !== 1) {
            Database::query(
                'INSERT INTO incidents (monitor_id, started_at, reason) VALUES (:monitor_id, NOW(), :reason)',
                ['monitor_id' => $monitor['id'], 'reason' => $result['message']]
            );
            $this->notifyWithCooldown($monitor, 'failed', $result['message']);
        }

        if ($result['status'] === 'online' && $openIncident) {
            $started = strtotime((string) $openIncident['started_at']);
            $duration = max(0, time() - $started);
            Database::query(
                'UPDATE incidents SET resolved_at = NOW(), duration = :duration WHERE id = :id',
                ['duration' => $duration, 'id' => $openIncident['id']]
            );
            $this->notifyWithCooldown($monitor, 'recovered', 'Recovered after ' . moneyless_duration($duration) . '.');
        }
    }

    private function notifyWithCooldown(array $monitor, string $event, string $message): void
    {
        $last = !empty($monitor['last_notified_at']) ? strtotime((string) $monitor['last_notified_at']) : 0;
        $cooldown = max(5, (int) ($monitor['notification_cooldown'] ?? 30)) * 60;
        if ($event !== 'recovered' && $last > 0 && time() - $last < $cooldown) {
            return;
        }

        $this->notifications->notifyMonitor($monitor, $event, $message);
        Database::query('UPDATE monitors SET last_notified_at = NOW() WHERE id = :id', ['id' => $monitor['id']]);
    }

    private function executeCheck(array $monitor): array
    {
        $start = microtime(true);
        $type = strtolower((string) $monitor['monitor_type']);
        $timeout = max(1, (int) $monitor['timeout']);

        try {
            $result = match ($type) {
                'website', 'http', 'https', 'api' => $this->checkHttp($monitor, $timeout),
                'database' => $this->checkDatabase($monitor, $timeout),
                'tcp', 'tcp_port' => $this->checkTcp($monitor, $timeout),
                'ping' => $this->checkPing($monitor, $timeout),
                'ssl' => $this->checkSslOnly($monitor, $timeout),
                default => ['status' => 'offline', 'http_code' => null, 'message' => 'Unsupported monitor type.'],
            };
        } catch (Throwable $e) {
            $result = ['status' => 'offline', 'http_code' => null, 'message' => $e->getMessage()];
        }

        $result['response_time'] = (int) round((microtime(true) - $start) * 1000);

        return $result;
    }

    private function checkHttp(array $monitor, int $timeout): array
    {
        $target = $this->normalizeUrl((string) $monitor['target'], strtolower((string) $monitor['monitor_type']) === 'https');
        Security::assertMonitorTargetAllowed($target, (int) ($monitor['port'] ?? 0));
        $expected = (int) ($monitor['expected_status_code'] ?: 200);
        $body = '';
        $code = null;
        $error = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($target);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => (bool) config('follow_http_redirects', false),
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'RawPHP-UptimeMonitor/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'user_agent' => 'RawPHP-UptimeMonitor/1.0',
                    'follow_location' => (int) config('follow_http_redirects', false),
                    'max_redirects' => 3,
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = (string) @file_get_contents($target, false, $context);
            $headers = $http_response_header ?? [];
            if ($headers && preg_match('/\s(\d{3})\s/', $headers[0], $matches)) {
                $code = (int) $matches[1];
            }
            $error = $body === '' && !$code ? 'HTTP request failed.' : null;
        }

        if ($error) {
            return ['status' => 'offline', 'http_code' => $code, 'message' => $error];
        }

        if ($code !== $expected) {
            return ['status' => 'offline', 'http_code' => $code, 'message' => "Expected HTTP {$expected}, got {$code}."];
        }

        if (!empty($monitor['keyword']) && stripos($body, (string) $monitor['keyword']) === false) {
            return ['status' => 'offline', 'http_code' => $code, 'message' => 'Expected keyword was not found in response.'];
        }

        $ssl = ((int) ($monitor['ssl_monitor'] ?? 0) === 1 || str_starts_with($target, 'https://'))
            ? $this->sslDetails($target, $timeout)
            : [];

        return ['status' => 'online', 'http_code' => $code, 'message' => 'HTTP check passed.'] + $ssl;
    }

    private function checkDatabase(array $monitor, int $timeout): array
    {
        Security::assertMonitorTargetAllowed((string) $monitor['target'], (int) ($monitor['port'] ?? 0));
        $parts = parse_url((string) $monitor['target']);
        if (!$parts || empty($parts['host'])) {
            return ['status' => 'offline', 'http_code' => null, 'message' => 'Use mysql://user:pass@host:port/database as the target.'];
        }

        $db = ltrim((string) ($parts['path'] ?? ''), '/');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $parts['host'], $parts['port'] ?? 3306, $db);
        $pdo = new PDO($dsn, rawurldecode((string) ($parts['user'] ?? '')), rawurldecode((string) ($parts['pass'] ?? '')), [
            PDO::ATTR_TIMEOUT => $timeout,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->query('SELECT 1');

        return ['status' => 'online', 'http_code' => null, 'message' => 'Database connection passed.'];
    }

    private function checkTcp(array $monitor, int $timeout): array
    {
        Security::assertMonitorTargetAllowed((string) $monitor['target'], (int) ($monitor['port'] ?? 0));
        $host = $this->hostFromTarget((string) $monitor['target']);
        $port = (int) ($monitor['port'] ?: 80);
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['status' => 'offline', 'http_code' => null, 'message' => "TCP {$host}:{$port} unavailable: {$errstr}"];
        }

        fclose($socket);

        return ['status' => 'online', 'http_code' => null, 'message' => "TCP {$host}:{$port} is reachable."];
    }

    private function checkPing(array $monitor, int $timeout): array
    {
        Security::assertMonitorTargetAllowed((string) $monitor['target'], (int) ($monitor['port'] ?? 0));
        $host = $this->hostFromTarget((string) $monitor['target']);

        if (function_exists('exec')) {
            $arg = escapeshellarg($host);
            $flag = stripos(PHP_OS, 'WIN') === 0 ? '-n 1 -w ' . ($timeout * 1000) : '-c 1 -W ' . $timeout;
            @exec("ping {$flag} {$arg}", $output, $code);
            if ($code === 0) {
                return ['status' => 'online', 'http_code' => null, 'message' => 'Ping check passed.'];
            }
        }

        $fallback = $monitor;
        $fallback['port'] = $monitor['port'] ?: 80;

        return $this->checkTcp($fallback, $timeout);
    }

    private function checkSslOnly(array $monitor, int $timeout): array
    {
        $details = $this->sslDetails($this->normalizeUrl((string) $monitor['target'], true), $timeout);
        if (!$details) {
            return ['status' => 'offline', 'http_code' => null, 'message' => 'Unable to read SSL certificate.'];
        }

        $days = (int) ($details['ssl_days_remaining'] ?? 0);
        if ($days <= 0) {
            return ['status' => 'offline', 'http_code' => null, 'message' => 'SSL certificate has expired.'] + $details;
        }

        return ['status' => 'online', 'http_code' => null, 'message' => 'SSL certificate is valid.'] + $details;
    }

    private function sslDetails(string $url, int $timeout): array
    {
        Security::assertMonitorTargetAllowed($url, 443);
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return [];
        }

        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false]]);
        $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$client) {
            return [];
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return [];
        }

        $parsed = openssl_x509_parse($cert);
        $expires = (int) ($parsed['validTo_time_t'] ?? 0);
        if ($expires <= 0) {
            return [];
        }

        return [
            'ssl_expires_at' => date('Y-m-d H:i:s', $expires),
            'ssl_days_remaining' => (int) floor(($expires - time()) / 86400),
        ];
    }

    private function normalizeUrl(string $target, bool $https = false): string
    {
        if (!preg_match('#^https?://#i', $target)) {
            return ($https ? 'https://' : 'http://') . $target;
        }

        return $target;
    }

    private function hostFromTarget(string $target): string
    {
        $parts = parse_url($target);
        if ($parts && !empty($parts['host'])) {
            return trim((string) $parts['host'], '[]');
        }

        $target = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', trim($target)) ?? $target;
        $target = explode('/', $target)[0];
        if (str_contains($target, '@')) {
            $target = substr(strrchr($target, '@') ?: $target, 1);
        }
        if (str_starts_with($target, '[') && str_contains($target, ']')) {
            return trim(substr($target, 0, strpos($target, ']') + 1), '[]');
        }

        return explode(':', $target)[0];
    }
}

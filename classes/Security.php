<?php

declare(strict_types=1);

final class Security
{
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function redactTarget(?string $target): string
    {
        $target = trim((string) $target);
        if ($target === '') {
            return '';
        }

        $parts = parse_url($target);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return preg_replace('/\/\/([^:@\/]+):([^@\/]+)@/', '//***:***@', $target) ?? $target;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = '';
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $queryParams);
            foreach ($queryParams as $key => $value) {
                if (preg_match('/(token|secret|key|password|pass|auth|signature|credential)/i', (string) $key)) {
                    $queryParams[$key] = 'redacted';
                }
            }
            $queryString = http_build_query($queryParams);
            $query = $queryString !== '' ? '?' . $queryString : '';
        }

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }

    public static function assertMonitorTargetAllowed(string $target, ?int $port = null): void
    {
        if ((bool) config('allow_private_monitor_targets', false)) {
            return;
        }

        $host = self::extractHost($target);
        if ($host === '') {
            return;
        }

        if (self::isPrivateHost($host)) {
            throw new RuntimeException('Private, loopback, or reserved monitor targets are disabled by default.');
        }
    }

    private static function extractHost(string $target): string
    {
        $target = trim($target);
        $parts = parse_url($target);
        if ($parts && !empty($parts['host'])) {
            return trim((string) $parts['host'], '[]');
        }

        $withoutScheme = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $target) ?? $target;
        $host = explode('/', $withoutScheme)[0];
        if (str_contains($host, '@')) {
            $host = substr(strrchr($host, '@') ?: $host, 1);
        }

        return trim(explode(':', $host)[0], '[]');
    }

    private static function isPrivateHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (!$records) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }
}

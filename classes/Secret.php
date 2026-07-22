<?php

declare(strict_types=1);

final class Secret
{
    private const PREFIX = 'enc:';

    public static function encrypt(?string $value): string
    {
        $value = (string) $value;
        if ($value === '' || str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        $key = self::key();
        if ($key === null || !function_exists('openssl_encrypt')) {
            return $value;
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return $value;
        }

        return self::PREFIX . base64_encode($iv . $ciphertext);
    }

    public static function decrypt(?string $value): string
    {
        $value = (string) $value;
        if ($value === '' || !str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        $key = self::key();
        if ($key === null || !function_exists('openssl_decrypt')) {
            return '';
        }

        $payload = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) <= 16) {
            return '';
        }

        $iv = substr($payload, 0, 16);
        $ciphertext = substr($payload, 16);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plaintext === false ? '' : $plaintext;
    }

    private static function key(): ?string
    {
        $configured = (string) config('secret_key', '');
        if ($configured === '' || $configured === 'change-this-32-byte-secret-key') {
            return null;
        }

        return hash('sha256', $configured, true);
    }
}

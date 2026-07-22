<?php

declare(strict_types=1);

final class Setting
{
    private static ?array $settings = null;

    public static function all(): array
    {
        if (self::$settings === null) {
            self::$settings = Database::fetch('SELECT * FROM settings ORDER BY id ASC LIMIT 1') ?? [];
            foreach (['smtp_password', 'telegram_bot_token'] as $secretField) {
                if (isset(self::$settings[$secretField])) {
                    self::$settings[$secretField] = Secret::decrypt((string) self::$settings[$secretField]);
                }
            }
        }

        return self::$settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::all();

        return $settings[$key] ?? $default;
    }

    public static function update(array $data): void
    {
        $existing = self::all();
        foreach (['smtp_password', 'telegram_bot_token'] as $secretField) {
            if (array_key_exists($secretField, $data)) {
                if ((string) $data[$secretField] === '' && !empty($existing[$secretField])) {
                    $data[$secretField] = $existing[$secretField];
                }
                $data[$secretField] = Secret::encrypt((string) $data[$secretField]);
            }
        }
        if ($existing) {
            $sets = [];
            foreach ($data as $key => $value) {
                $sets[] = $key . ' = :' . $key;
            }
            Database::query('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = :id', $data + ['id' => $existing['id']]);
        } else {
            $columns = implode(', ', array_keys($data));
            $params = ':' . implode(', :', array_keys($data));
            Database::query("INSERT INTO settings ({$columns}) VALUES ({$params})", $data);
        }

        self::$settings = null;
    }
}

<?php

declare(strict_types=1);

final class Validator
{
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        return $errors;
    }

    public static function intRange(mixed $value, int $min, int $max): bool
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int !== false && $int >= $min && $int <= $max;
    }
}

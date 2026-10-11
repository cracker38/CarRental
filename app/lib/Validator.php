<?php
declare(strict_types=1);

final class Validator
{
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 190;
    }

    public static function password(string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'Password must be at least 10 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password must include uppercase, lowercase, and a number.';
        }
        return null;
    }

    public static function name(string $value, string $label = 'Name'): ?string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 100) {
            return $label . ' is required and must be 100 characters or fewer.';
        }
        return null;
    }

    public static function phone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        if (strlen($phone) > 30 || !preg_match('/^[0-9+\-\s()]{6,30}$/', $phone)) {
            return 'Enter a valid phone number.';
        }
        return null;
    }
}

<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? '';
        if (!is_string($expected) || $expected === '' || !is_string($token)) {
            return false;
        }
        return hash_equals($expected, $token);
    }

    public static function requireValid(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!self::validate(is_string($token) ? $token : null)) {
            http_response_code(419);
            flash_set('danger', 'Your session token expired. Please submit the form again.');
            $redirect = $_SERVER['HTTP_REFERER'] ?? base_url('admin/dashboard.php');
            header('Location: ' . $redirect);
            exit;
        }
    }
}

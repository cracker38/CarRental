<?php
declare(strict_types=1);

/**
 * Minimal bootstrap for the CarRental project.
 * Database defaults match a typical local XAMPP installation.
 * Review DB credentials and session settings before deployment.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** Change these defaults if your MySQL settings differ. */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'carrental';
const DB_USER = 'root';
const DB_PASS = '';

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function requireValid(): void
    {
        $sent = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? '';
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            exit('Your form session expired or the security token was invalid. Go back, refresh the page, and try again.');
        }
    }
}

final class Auth
{
    /** @return array{ok:bool,error?:string} */
    public static function attempt(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            return ['ok' => false, 'error' => 'Enter your work email and password.'];
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT u.user_id, u.password_hash, u.account_status
                 FROM users u WHERE LOWER(u.email) = :email LIMIT 1"
            );
            $stmt->execute(['email' => $email]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            error_log('Login database error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Sign-in is temporarily unavailable. Check the database configuration.'];
        }
        if (!$row || !password_verify($password, (string)$row['password_hash']) || $row['account_status'] !== 'active') {
            return ['ok' => false, 'error' => 'The email or password is incorrect, or the account is not active.'];
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['user_id'];
        $update = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = :id');
        $update->execute(['id' => (int)$row['user_id']]);
        return ['ok' => true];
    }
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function requireLogin(): array
{
    if (!is_logged_in()) {
        $_SESSION['_intended'] = (string)($_SERVER['REQUEST_URI'] ?? 'admin/dashboard.php');
        redirect('login.php');
    }
    $stmt = Database::connection()->prepare(
        'SELECT u.user_id, u.role_id, u.first_name, u.last_name, u.email, u.account_status,
                u.must_change_password, u.last_login_at, r.role_name
         FROM users u JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = :id AND u.account_status = \'active\' LIMIT 1'
    );
    $stmt->execute(['id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        redirect('login.php');
    }
    return $user;
}

function hasPermission(string $permission): bool
{
    if (!is_logged_in()) return false;
    static $cache = [];
    $userId = (int)$_SESSION['user_id'];
    $key = $userId . ':' . $permission;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.permission_id = rp.permission_id
             WHERE u.user_id = :uid AND u.account_status = \'active\' AND p.permission_key = :perm LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'perm' => $permission]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Permission check failed: ' . $e->getMessage());
        $cache[$key] = false;
    }
    return $cache[$key];
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool
{
    return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(Csrf::token()) . '">';
}

function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/CarRental/index.php'));
    $segments = array_values(array_filter(explode('/', trim($script, '/')), static fn($v) => $v !== ''));
    $root = '/' . ($segments[0] ?? 'CarRental');
    if ($path === '') return $root . '/';
    return $root . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    if (preg_match('~^https?://~i', $path)) {
        http_response_code(400);
        exit('External redirects are not permitted.');
    }
    header('Location: ' . base_url($path));
    exit;
}

function setting(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            $rows = Database::connection()->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
            foreach ($rows as $row) $settings[(string)$row['setting_key']] = $row['setting_value'];
        } catch (Throwable $e) {
            // Settings are optional while the database is being set up.
        }
    }
    return $settings[$key] ?? $default;
}

function greeting(): string
{
    $hour = (int)date('G');
    return $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
}

function format_datetime(mixed $value, string $fallback = '—'): string
{
    if (!$value) return $fallback;
    try { return (new DateTimeImmutable((string)$value))->format('d M Y, H:i'); }
    catch (Throwable $e) { return $fallback; }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

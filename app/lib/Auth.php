<?php
declare(strict_types=1);

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::enforceTimeout();
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name((string) config('session_name', 'CRMSSESSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $GLOBALS['crms_cookie_path'] ?? '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::enforceTimeout();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['auth_ok']) && $_SESSION['auth_ok'] === true;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (!empty($_SESSION['_user_cache']) && is_array($_SESSION['_user_cache'])) {
            return $_SESSION['_user_cache'];
        }
        $user = self::loadUser((int) $_SESSION['user_id']);
        if (!$user || $user['account_status'] !== 'active') {
            self::logout(false);
            return null;
        }
        $_SESSION['_user_cache'] = $user;
        return $user;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? base_url('admin/dashboard.php');
            flash_set('warning', 'Please sign in to continue.');
            redirect('login.php');
        }
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ((int) $user['must_change_password'] === 1 && $script !== 'change-password.php' && $script !== 'logout.php') {
            flash_set('warning', 'You must change your password before using the system.');
            redirect('admin/change-password.php');
        }
        return $user;
    }

    public static function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return ['ok' => false, 'error' => 'Enter your email and password.'];
        }

        if (self::tooManyAttempts($email)) {
            Audit::record(null, 'login.locked', 'user', $email, 'Login blocked by rate limit.', 'denied');
            return ['ok' => false, 'error' => 'Too many sign-in attempts. Please wait and try again.'];
        }

        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.role_name
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE LOWER(u.email) = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        $valid = $user && password_verify($password, $user['password_hash']);
        if (!$valid) {
            Audit::record($user['user_id'] ?? null, 'login.failed', 'user', $email, 'Failed login attempt.', 'failure');
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        if ($user['account_status'] !== 'active') {
            Audit::record((int) $user['user_id'], 'login.denied', 'user', (string) $user['user_id'], 'Login denied because the account is not active.', 'denied');
            return ['ok' => false, 'error' => 'This account cannot sign in. Contact an administrator.'];
        }

        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['auth_ok'] = true;
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['_user_cache']);

        Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = :id')
            ->execute([':id' => $user['user_id']]);

        Audit::record((int) $user['user_id'], 'login.success', 'user', (string) $user['user_id'], 'User signed in.', 'success');
        return ['ok' => true, 'user' => self::user()];
    }

    public static function logout(bool $audit = true): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($audit && $userId) {
            Audit::record((int) $userId, 'logout', 'user', (string) $userId, 'User signed out.', 'success');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function loadUser(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.user_id, u.role_id, u.first_name, u.last_name, u.email, u.phone,
                    u.account_status, u.must_change_password, u.last_login_at, u.created_at, u.updated_at,
                    r.role_name, r.is_system_role
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE u.user_id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }
        $user['permissions'] = self::permissionsForRole((int) $user['role_id'], $user['role_name']);
        return $user;
    }

    public static function permissionsForRole(int $roleId, string $roleName = ''): array
    {
        if ($roleName === 'System Administrator') {
            $keys = Database::connection()->query('SELECT permission_key FROM permissions ORDER BY permission_key')->fetchAll(PDO::FETCH_COLUMN);
            return array_values($keys);
        }
        $stmt = Database::connection()->prepare(
            'SELECT p.permission_key
             FROM role_permissions rp
             INNER JOIN permissions p ON p.permission_id = rp.permission_id
             WHERE rp.role_id = :role_id
             ORDER BY p.permission_key'
        );
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function tooManyAttempts(string $email): bool
    {
        $max = (int) config('login_max_attempts', 5);
        $window = (int) config('login_attempt_window_minutes', 15);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE action IN ('login.failed', 'login.locked')
               AND created_at >= DATE_SUB(NOW(), INTERVAL :window MINUTE)
               AND (ip_address = :ip OR entity_id = :email)"
        );
        $stmt->bindValue(':window', $window, PDO::PARAM_INT);
        $stmt->bindValue(':ip', client_ip());
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        return (int) $stmt->fetchColumn() >= $max;
    }

    private static function enforceTimeout(): void
    {
        if (!self::check()) {
            return;
        }
        $idle = (int) Settings::get('session_idle_timeout', config('session_idle_minutes', 30));
        $last = (int) ($_SESSION['last_activity'] ?? 0);
        if ($idle > 0 && $last > 0 && (time() - $last) > ($idle * 60)) {
            self::logout(false);
            session_start();
            flash_set('warning', 'Your session expired due to inactivity. Please sign in again.');
            return;
        }
        $_SESSION['last_activity'] = time();
    }
}

<?php
declare(strict_types=1);

final class PasswordReset
{
    public static function request(string $email): void
    {
        $email = strtolower(trim($email));
        if (!Validator::email($email) || self::tooManyRequests($email)) {
            Audit::record(null, 'password_reset.request', 'user', $email, 'Password reset request ignored or rate limited.', 'denied');
            return;
        }

        $stmt = Database::connection()->prepare('SELECT user_id, account_status FROM users WHERE LOWER(email) = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user || $user['account_status'] !== 'active') {
            Audit::record($user['user_id'] ?? null, 'password_reset.request', 'user', $email, 'Password reset requested for unknown or inactive account.', 'failure');
            return;
        }

        $ttl = (int) config('password_reset_ttl_minutes', 60);
        $raw = bin2hex(random_bytes(32));
        $hash = self::hashToken($raw);
        $expires = (new DateTimeImmutable('now'))->modify('+' . $ttl . ' minutes')->format('Y-m-d H:i:s');

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL')
                ->execute([':id' => $user['user_id']]);
            $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)')
                ->execute([':uid' => $user['user_id'], ':hash' => $hash, ':exp' => $expires]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Audit::record((int) $user['user_id'], 'password_reset.request', 'user', (string) $user['user_id'], 'Password reset token created.', 'success');
        self::deliver((int) $user['user_id'], $raw);
    }

    public static function reset(string $rawToken, string $password): array
    {
        $error = Validator::password($password);
        if ($error) {
            return ['ok' => false, 'error' => $error];
        }
        $hash = self::hashToken($rawToken);
        $stmt = Database::connection()->prepare(
            'SELECT t.*, u.account_status
             FROM password_reset_tokens t
             INNER JOIN users u ON u.user_id = t.user_id
             WHERE t.token_hash = :hash
             LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch();
        if (!$row || $row['used_at'] !== null || $row['account_status'] !== 'active' || strtotime($row['expires_at']) < time()) {
            Audit::record($row['user_id'] ?? null, 'password_reset.failed', 'password_reset_tokens', null, 'Invalid or expired password reset attempt.', 'failure');
            return ['ok' => false, 'error' => 'This reset link is invalid or has expired.'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE user_id = :id')
                ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['user_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = :id')
                ->execute([':id' => $row['token_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
                ->execute([':uid' => $row['user_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Audit::record((int) $row['user_id'], 'password_reset.success', 'user', (string) $row['user_id'], 'Password was reset with a one-time token.', 'success');
        return ['ok' => true];
    }

    public static function hashToken(string $raw): string
    {
        return hash_hmac('sha256', $raw, (string) config('app_key'));
    }

    private static function tooManyRequests(string $email): bool
    {
        $max = (int) config('reset_max_attempts', 3);
        $window = (int) config('reset_attempt_window_minutes', 60);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE action = 'password_reset.request'
               AND created_at >= DATE_SUB(NOW(), INTERVAL :window MINUTE)
               AND (ip_address = :ip OR entity_id = :email)"
        );
        $stmt->bindValue(':window', $window, PDO::PARAM_INT);
        $stmt->bindValue(':ip', client_ip());
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        return (int) $stmt->fetchColumn() >= $max;
    }

    private static function deliver(int $userId, string $rawToken): void
    {
        $link = base_url('reset-password.php?token=' . urlencode($rawToken));
        if (!config('debug')) {
            return;
        }
        $dir = BASE_PATH . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $line = sprintf("[%s] user_id=%d reset_link=%s\n", date('c'), $userId, $link);
        file_put_contents($dir . '/password_resets.log', $line, FILE_APPEND | LOCK_EX);
    }
}

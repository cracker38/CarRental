<?php
declare(strict_types=1);

final class Audit
{
    public static function record(
        ?int $userId,
        string $action,
        string $entityType,
        ?string $entityId,
        ?string $description,
        string $result = 'success',
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $oldValues = self::redact($oldValues);
        $newValues = self::redact($newValues);

        $sql = 'INSERT INTO audit_logs
            (user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent, result)
            VALUES (:user_id, :action, :entity_type, :entity_id, :description, :old_values, :new_values, :ip, :ua, :result)';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => substr($action, 0, 100),
            ':entity_type' => substr($entityType, 0, 100),
            ':entity_id' => $entityId !== null ? substr($entityId, 0, 100) : null,
            ':description' => $description !== null ? substr($description, 0, 1000) : null,
            ':old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            ':new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            ':ip' => client_ip(),
            ':ua' => user_agent(),
            ':result' => in_array($result, ['success', 'failure', 'denied'], true) ? $result : 'success',
        ]);
    }

    private static function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        $sensitive = ['password', 'password_hash', 'password_confirm', 'current_password', 'token', 'token_hash', 'csrf_token', 'session_id'];
        foreach ($values as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, $sensitive, true) || str_contains($name, 'password') || str_contains($name, 'token') || str_contains($name, 'secret')) {
                $values[$key] = '[redacted]';
            }
        }
        return $values;
    }
}

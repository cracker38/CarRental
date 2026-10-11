<?php
declare(strict_types=1);

final class RoleService
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM roles ORDER BY role_id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM roles WHERE role_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function permissions(): array
    {
        return Database::connection()->query('SELECT * FROM permissions ORDER BY permission_key')->fetchAll();
    }

    public static function permissionIdsForRole(int $roleId): array
    {
        $stmt = Database::connection()->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :id');
        $stmt->execute([':id' => $roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function savePermissions(int $roleId, array $permissionIds, array $actor): array
    {
        $role = self::find($roleId);
        if (!$role) {
            return ['ok' => false, 'error' => 'Role was not found.'];
        }
        if ($role['role_name'] === 'System Administrator') {
            return ['ok' => false, 'error' => 'System Administrator permissions are managed by the application and cannot be reduced here.'];
        }

        $valid = Database::connection()->query('SELECT permission_id FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
        $valid = array_map('intval', $valid);
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        foreach ($permissionIds as $pid) {
            if (!in_array($pid, $valid, true)) {
                return ['ok' => false, 'error' => 'One or more permissions are invalid.'];
            }
        }

        $old = self::permissionIdsForRole($roleId);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :id')->execute([':id' => $roleId]);
            $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
            foreach ($permissionIds as $pid) {
                $insert->execute([':role_id' => $roleId, ':permission_id' => $pid]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Audit::record((int) $actor['user_id'], 'role.permissions_update', 'role', (string) $roleId, 'Updated role permissions for ' . $role['role_name'], 'success', ['permission_ids' => $old], ['permission_ids' => $permissionIds]);
        return ['ok' => true];
    }

    public static function create(string $name, string $description, array $actor): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 60) {
            return ['ok' => false, 'error' => 'Role name is required and must be 60 characters or fewer.'];
        }
        try {
            Database::connection()->prepare(
                'INSERT INTO roles (role_name, description, is_system_role) VALUES (:n, :d, 0)'
            )->execute([':n' => $name, ':d' => substr(trim($description), 0, 255) ?: null]);
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => 'A role with that name already exists.'];
        }
        $id = (int) Database::connection()->lastInsertId();
        Audit::record((int) $actor['user_id'], 'role.create', 'role', (string) $id, 'Created role ' . $name, 'success');
        return ['ok' => true, 'id' => $id];
    }
}

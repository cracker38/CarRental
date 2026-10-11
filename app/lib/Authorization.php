<?php
declare(strict_types=1);

final class Authorization
{
    private const ALIASES = [
        'users.view' => ['users.manage'],
        'users.create' => ['users.manage'],
        'users.update' => ['users.manage'],
        'users.deactivate' => ['users.manage'],
        'roles.view' => ['roles.manage'],
        'payments.view' => ['payments.manage', 'invoices.view'],
        'reports.view' => ['reports.operational', 'reports.financial'],
        'audit_logs.view' => ['audit.view'],
    ];

    public static function allows(string $permission, ?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user) {
            return false;
        }
        if (($user['role_name'] ?? '') === 'System Administrator') {
            return true;
        }
        $owned = $user['permissions'] ?? [];
        if (in_array($permission, $owned, true)) {
            return true;
        }
        foreach (self::ALIASES[$permission] ?? [] as $alias) {
            if (in_array($alias, $owned, true)) {
                return true;
            }
        }
        return false;
    }

    public static function canAssignRole(array $actor, array $targetRole): bool
    {
        if (!self::allows('users.manage', $actor)) {
            return false;
        }
        $targetName = $targetRole['role_name'] ?? '';
        if ($targetName === 'System Administrator') {
            return ($actor['role_name'] ?? '') === 'System Administrator';
        }
        return true;
    }
}

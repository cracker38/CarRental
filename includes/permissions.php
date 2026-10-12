<?php
/**
 * Permission helpers
 */
require_once __DIR__ . '/../config/database.php';

function hasPermission(string $permissionKey): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    if (!empty($_SESSION['is_admin'])) {
        return true;
    }
    $perms = $_SESSION['permissions'] ?? [];
    return in_array($permissionKey, $perms, true);
}

function requirePermission(string $permissionKey): void
{
    requireLogin();
    if (!hasPermission($permissionKey)) {
        http_response_code(403);
        $pageTitle = 'Access Denied';
        require __DIR__ . '/header.php';
        echo '<div class="content-area"><div class="alert alert-error"><i class="fas fa-lock"></i> You do not have permission to perform this action.</div>';
        echo '<p><a href="' . BASE_URL . '/index.php" class="btn btn-outline">Go to Dashboard</a></p></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

function loadUserPermissions(PDO $pdo, int $roleId): void
{
    $roleStmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = ?');
    $roleStmt->execute([$roleId]);
    $roleName = $roleStmt->fetchColumn();

    $_SESSION['role_name'] = $roleName ?: '';
    $_SESSION['is_admin']  = ($roleName === 'System Administrator');

    if ($_SESSION['is_admin']) {
        $_SESSION['permissions'] = [];
        return;
    }

    $stmt = $pdo->prepare("
        SELECT p.permission_key
        FROM role_permissions rp
        JOIN permissions p ON p.permission_id = rp.permission_id
        WHERE rp.role_id = ?
    ");
    $stmt->execute([$roleId]);
    $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

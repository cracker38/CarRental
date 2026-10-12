<?php
require_once __DIR__ . '/../config/app.php';

function user_has_permission(PDO $pdo, string $key): bool
{
    $uid = current_user_id();
    if (!$uid) return false;
    $s = $pdo->prepare(
        "SELECT 1
         FROM users u
         JOIN role_permissions rp ON rp.role_id = u.role_id
         JOIN permissions p ON p.permission_id = rp.permission_id
         WHERE u.user_id = ? AND u.account_status = 'active' AND p.permission_key = ?
         LIMIT 1"
    );
    $s->execute([$uid, $key]);
    return (bool)$s->fetchColumn();
}

function require_permission(PDO $pdo, string $key): void
{
    if (!AUTH_ENFORCE) return;
    start_session();
    if (!current_user_id()) redirect(LOGIN_URL);
    if (!user_has_permission($pdo, $key)) {
        http_response_code(403);
        die('You do not have permission to do this.');
    }
}
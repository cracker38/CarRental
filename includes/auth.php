<?php
/**
 * Authentication helpers
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/permissions.php';

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? BASE_URL . '/';
        redirect('/auth/login.php');
    }
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id'    => $_SESSION['user_id'],
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name'  => $_SESSION['last_name'] ?? '',
        'email'      => $_SESSION['email'] ?? '',
        'role_name'  => $_SESSION['role_name'] ?? '',
        'role_id'    => $_SESSION['role_id'] ?? null,
    ];
}

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle = $pageTitle ?? 'Dashboard';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Car Rental</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="main-wrapper">
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <h2 class="page-title"><?= e($pageTitle) ?></h2>
        <div class="user-area">
            <span class="user-name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
            <span class="badge"><?= e($user['role_name']) ?></span>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline btn-sm" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>
    <main class="content-area">
<?php
$success = getFlash('success');
$error   = getFlash('error');
if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif;
if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

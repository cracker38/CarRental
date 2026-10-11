<?php
declare(strict_types=1);
$user = current_user();
$pageTitle = $pageTitle ?? setting('app_name', 'CRMS');
$currentPage = $currentPage ?? '';
$appName = (string) setting('app_name', 'CRMS');
$businessName = (string) setting('business_name', 'Car Rental');
$moduleExists = static function (string $relative): bool {
    return is_file(BASE_PATH . '/' . $relative);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="crms-body">
<div class="d-flex" id="app-shell">
    <aside class="crms-sidebar" id="sidebar">
        <div class="brand">
            <span class="brand-mark"><i class="bi bi-speedometer2"></i></span>
            <div>
                <strong><?= e($appName) ?></strong>
                <small><?= e($businessName) ?></small>
            </div>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= e(base_url('admin/dashboard.php')) ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <?php if (hasPermission('users.manage')): ?>
                <a class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>" href="<?= e(base_url('admin/users.php')) ?>">
                    <i class="bi bi-people"></i> Users
                </a>
            <?php endif; ?>
            <?php if (hasPermission('roles.manage')): ?>
                <a class="nav-link <?= $currentPage === 'roles' ? 'active' : '' ?>" href="<?= e(base_url('admin/roles.php')) ?>">
                    <i class="bi bi-shield-lock"></i> Roles
                </a>
            <?php endif; ?>
            <?php if (hasPermission('audit.view')): ?>
                <a class="nav-link <?= $currentPage === 'audit' ? 'active' : '' ?>" href="<?= e(base_url('admin/audit-logs.php')) ?>">
                    <i class="bi bi-journal-text"></i> Audit logs
                </a>
            <?php endif; ?>
            <?php if (hasPermission('settings.manage')): ?>
                <a class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= e(base_url('admin/settings.php')) ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
            <?php endif; ?>
            <div class="nav-section">Operations</div>
            <?php if (hasPermission('vehicles.view') && $moduleExists('vehicles/index.php')): ?>
                <a class="nav-link" href="<?= e(base_url('vehicles/index.php')) ?>"><i class="bi bi-car-front"></i> Vehicles</a>
            <?php endif; ?>
            <?php if (hasPermission('customers.view') && $moduleExists('customers/index.php')): ?>
                <a class="nav-link" href="<?= e(base_url('customers/index.php')) ?>"><i class="bi bi-person-vcard"></i> Customers</a>
            <?php endif; ?>
            <?php if (hasPermission('reservations.view') && $moduleExists('reservations/index.php')): ?>
                <a class="nav-link" href="<?= e(base_url('reservations/index.php')) ?>"><i class="bi bi-calendar-check"></i> Reservations</a>
            <?php endif; ?>
            <?php if (hasPermission('rentals.manage') && $moduleExists('rentals/index.php')): ?>
                <a class="nav-link" href="<?= e(base_url('rentals/index.php')) ?>"><i class="bi bi-key"></i> Rentals</a>
            <?php endif; ?>
            <?php if (hasPermission('payments.view') && $moduleExists('payments/index.php')): ?>
                <a class="nav-link" href="<?= e(base_url('payments/index.php')) ?>"><i class="bi bi-cash-stack"></i> Payments</a>
            <?php endif; ?>
            <?php if (hasPermission('maintenance.manage') && $moduleExists('maintenance/index.php')): ?>
                <a class="nav-link" href="<?= e(base_url('maintenance/index.php')) ?>"><i class="bi bi-wrench-adjustable"></i> Maintenance</a>
            <?php endif; ?>
            <?php if (hasPermission('reports.view') && $moduleExists('reports/index.php')): ?>
                <a class="nav-link" href="<?= e(base_url('reports/index.php')) ?>"><i class="bi bi-graph-up"></i> Reports</a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="crms-main">
        <header class="crms-topbar">
            <button class="btn btn-link d-lg-none" type="button" id="sidebarToggle" aria-label="Open menu">
                <i class="bi bi-list fs-3"></i>
            </button>
            <div class="ms-auto d-flex align-items-center gap-3">
                <?php if ($user): ?>
                    <div class="user-chip">
                        <div class="avatar"><?= e(strtoupper(substr((string) $user['first_name'], 0, 1))) ?></div>
                        <div>
                            <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                            <small><?= e($user['role_name']) ?></small>
                        </div>
                    </div>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/change-password.php')) ?>">Password</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(base_url('logout.php')) ?>">Sign out</a>
                <?php endif; ?>
            </div>
        </header>
        <main class="crms-content">
            <?php require BASE_PATH . '/includes/flash.php'; ?>

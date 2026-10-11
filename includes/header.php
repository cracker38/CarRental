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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="crms-body">
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="d-flex" id="app-shell">
    <aside class="crms-sidebar" id="sidebar">
        <div class="brand">
            <span class="brand-mark"><i class="bi bi-car-front-fill"></i></span>
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
        <div class="sidebar-foot">
            <?= e((string) setting('default_currency', 'RWF')) ?> · <?= e((string) setting('timezone', 'Africa/Kigali')) ?>
        </div>
    </aside>
    <div class="crms-main">
        <header class="crms-topbar">
            <button class="btn btn-link d-lg-none p-0 text-dark" type="button" id="sidebarToggle" aria-label="Open menu">
                <i class="bi bi-list fs-2"></i>
            </button>
            <div class="topbar-title d-none d-md-block"><?= e($pageTitle) ?></div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <?php if ($user): ?>
                    <div class="dropdown">
                        <button class="btn btn-link text-decoration-none text-dark p-0" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-chip">
                                <div class="avatar"><?= e(initials((string) $user['first_name'], (string) $user['last_name'])) ?></div>
                                <div class="d-none d-sm-block text-start">
                                    <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                    <small><?= e($user['role_name']) ?></small>
                                </div>
                                <i class="bi bi-chevron-down small text-secondary"></i>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header"><?= e($user['email']) ?></h6></li>
                            <li><a class="dropdown-item" href="<?= e(base_url('admin/change-password.php')) ?>"><i class="bi bi-key me-2"></i>Change password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </header>
        <main class="crms-content">
            <?php require BASE_PATH . '/includes/flash.php'; ?>

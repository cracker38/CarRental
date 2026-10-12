<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireLogin();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

$pdo = Database::connection();
$stats = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active_users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'active'")->fetchColumn(),
    'roles' => (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn(),
    'audit' => (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
];

$safeCount = static function (PDO $pdo, string $table): int {
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
};

$ops = [
    ['label' => 'Vehicles', 'value' => $safeCount($pdo, 'vehicles'), 'icon' => 'bi-car-front'],
    ['label' => 'Customers', 'value' => $safeCount($pdo, 'customers'), 'icon' => 'bi-person-vcard'],
    ['label' => 'Reservations', 'value' => $safeCount($pdo, 'reservations'), 'icon' => 'bi-calendar-check'],
    ['label' => 'Rentals', 'value' => $safeCount($pdo, 'rentals'), 'icon' => 'bi-key'],
];

$recent = [];
if (hasPermission('audit.view')) {
    $recent = $pdo->query(
        'SELECT a.action, a.result, a.created_at, a.description, u.first_name, u.last_name
         FROM audit_logs a
         LEFT JOIN users u ON u.user_id = a.user_id
         ORDER BY a.audit_log_id DESC
         LIMIT 7'
    )->fetchAll();
}

require BASE_PATH . '/includes/header.php';
?>
<section class="dash-hero">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <span class="dash-chip"><i class="bi bi-sunrise"></i> <?= e(greeting()) ?></span>
            <span class="dash-chip"><i class="bi bi-person-badge"></i> <?= e($user['role_name']) ?></span>
            <h1 class="h3 mb-2">Welcome back, <?= e($user['first_name']) ?></h1>
            <p>You are managing <?= e((string) setting('business_name')) ?> in <?= e((string) setting('default_currency', 'RWF')) ?>. Last sign-in: <?= e(format_datetime($user['last_login_at'] ?? null, 'This session')) ?>.</p>
        </div>
        <?php if (hasPermission('users.manage')): ?>
            <a class="btn btn-light fw-bold" href="<?= e(base_url('admin/user-form.php')) ?>"><i class="bi bi-plus-lg me-1"></i> New user</a>
        <?php endif; ?>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <?php if (hasPermission('users.manage')): ?><a class="kpi-link" href="<?= e(base_url('admin/users.php')) ?>"><?php endif; ?>
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon navy"><i class="bi bi-people"></i></div>
            <div><div class="label">Users</div><div class="value"><?= (int) $stats['users'] ?></div></div>
        </div></div>
        <?php if (hasPermission('users.manage')): ?></a><?php endif; ?>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon teal"><i class="bi bi-person-check"></i></div>
            <div><div class="label">Active</div><div class="value"><?= (int) $stats['active_users'] ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <?php if (hasPermission('roles.manage')): ?><a class="kpi-link" href="<?= e(base_url('admin/roles.php')) ?>"><?php endif; ?>
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon gold"><i class="bi bi-shield-lock"></i></div>
            <div><div class="label">Roles</div><div class="value"><?= (int) $stats['roles'] ?></div></div>
        </div></div>
        <?php if (hasPermission('roles.manage')): ?></a><?php endif; ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php if (hasPermission('audit.view')): ?><a class="kpi-link" href="<?= e(base_url('admin/audit-logs.php')) ?>"><?php endif; ?>
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon slate"><i class="bi bi-journal-text"></i></div>
            <div><div class="label">Audit events</div><div class="value"><?= (int) $stats['audit'] ?></div></div>
        </div></div>
        <?php if (hasPermission('audit.view')): ?></a><?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card crms-card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Quick actions</h2>
                <div class="d-grid gap-2">
                    <?php if (hasPermission('users.manage')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/users.php')) ?>"><i class="bi bi-people"></i><span>Manage staff accounts</span></a>
                    <?php endif; ?>
                    <?php if (hasPermission('roles.manage')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/roles.php')) ?>"><i class="bi bi-sliders"></i><span>Review permissions</span></a>
                    <?php endif; ?>
                    <?php if (hasPermission('settings.manage')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/settings.php')) ?>"><i class="bi bi-building"></i><span>Business settings</span></a>
                    <?php endif; ?>
                    <?php if (hasPermission('audit.view')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/audit-logs.php')) ?>"><i class="bi bi-search"></i><span>Inspect audit history</span></a>
                    <?php endif; ?>
                    <a class="quick-link" href="<?= e(base_url('admin/change-password.php')) ?>"><i class="bi bi-key"></i><span>Change my password</span></a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card crms-card h-100">
            <div class="card-body">
                <h2 class="h5 mb-1">Operations snapshot</h2>
                <p class="text-secondary small">Live counts from shared CRMS tables.</p>
                <div class="row g-2">
                    <?php foreach ($ops as $item): ?>
                        <div class="col-6">
                            <div class="ops-tile">
                                <i class="bi <?= e($item['icon']) ?>"></i>
                                <div class="value fs-4"><?= (int) $item['value'] ?></div>
                                <div class="label"><?= e($item['label']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card crms-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Recent activity</h2>
                    <?php if (hasPermission('audit.view')): ?>
                        <a class="small" href="<?= e(base_url('admin/audit-logs.php')) ?>">View all</a>
                    <?php endif; ?>
                </div>
                <?php if (!hasPermission('audit.view')): ?>
                    <p class="text-secondary mb-0">Audit history is restricted to authorized roles.</p>
                <?php elseif (!$recent): ?>
                    <div class="empty-state py-4"><i class="bi bi-inboxes"></i>No audit events yet.</div>
                <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                        <div class="activity-item">
                            <span class="activity-dot dot-<?= e($row['result']) ?>"></span>
                            <div>
                                <strong><?= e($row['action']) ?></strong>
                                <span class="badge badge-result-<?= e($row['result']) ?>"><?= e($row['result']) ?></span>
                                <div class="small text-secondary">
                                    <?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'System') ?>
                                    · <?= e(format_datetime($row['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

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
    'vehicles' => $safeCount($pdo, 'vehicles'),
    'customers' => $safeCount($pdo, 'customers'),
    'reservations' => $safeCount($pdo, 'reservations'),
    'rentals' => $safeCount($pdo, 'rentals'),
];

$recent = [];
if (hasPermission('audit.view')) {
    $recent = $pdo->query(
        'SELECT a.action, a.result, a.created_at, a.description, u.first_name, u.last_name
         FROM audit_logs a
         LEFT JOIN users u ON u.user_id = a.user_id
         ORDER BY a.audit_log_id DESC
         LIMIT 8'
    )->fetchAll();
}

require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumb">Overview</div>
        <h1 class="h3 mb-1"><?= e(greeting()) ?>, <?= e($user['first_name']) ?></h1>
        <p class="text-secondary mb-0">Administration for <?= e((string) setting('business_name')) ?> · <?= e((string) setting('default_currency', 'RWF')) ?></p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon navy"><i class="bi bi-people"></i></div>
            <div><div class="label">Users</div><div class="value"><?= (int) $stats['users'] ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon teal"><i class="bi bi-person-check"></i></div>
            <div><div class="label">Active</div><div class="value"><?= (int) $stats['active_users'] ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon gold"><i class="bi bi-shield-lock"></i></div>
            <div><div class="label">Roles</div><div class="value"><?= (int) $stats['roles'] ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card crms-card"><div class="card-body kpi">
            <div class="kpi-icon slate"><i class="bi bi-journal-text"></i></div>
            <div><div class="label">Audit events</div><div class="value"><?= (int) $stats['audit'] ?></div></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card crms-card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Quick actions</h2>
                <div class="d-grid gap-2">
                    <?php if (hasPermission('users.manage')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/user-form.php')) ?>"><i class="bi bi-person-plus"></i><span>Create a staff account</span></a>
                        <a class="quick-link" href="<?= e(base_url('admin/users.php')) ?>"><i class="bi bi-people"></i><span>Manage users</span></a>
                    <?php endif; ?>
                    <?php if (hasPermission('roles.manage')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/roles.php')) ?>"><i class="bi bi-sliders"></i><span>Review role permissions</span></a>
                    <?php endif; ?>
                    <?php if (hasPermission('settings.manage')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/settings.php')) ?>"><i class="bi bi-building"></i><span>Update business settings</span></a>
                    <?php endif; ?>
                    <?php if (hasPermission('audit.view')): ?>
                        <a class="quick-link" href="<?= e(base_url('admin/audit-logs.php')) ?>"><i class="bi bi-search"></i><span>Inspect audit history</span></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card crms-card h-100">
            <div class="card-body">
                <h2 class="h5">Operations snapshot</h2>
                <p class="text-secondary small">Live counts from shared CRMS tables. Other team members own those modules.</p>
                <div class="row g-3 text-center">
                    <div class="col-6"><div class="value fs-4"><?= (int) $ops['vehicles'] ?></div><div class="label">Vehicles</div></div>
                    <div class="col-6"><div class="value fs-4"><?= (int) $ops['customers'] ?></div><div class="label">Customers</div></div>
                    <div class="col-6"><div class="value fs-4"><?= (int) $ops['reservations'] ?></div><div class="label">Reservations</div></div>
                    <div class="col-6"><div class="value fs-4"><?= (int) $ops['rentals'] ?></div><div class="label">Rentals</div></div>
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

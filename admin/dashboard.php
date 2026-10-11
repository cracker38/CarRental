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
        <h1 class="h3 mb-1">Welcome, <?= e($user['first_name']) ?></h1>
        <p class="text-secondary mb-0">System administration overview for <?= e((string) setting('business_name')) ?>.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card stat-card"><div class="card-body">
            <div class="label">Users</div>
            <div class="value"><?= (int) $stats['users'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card"><div class="card-body">
            <div class="label">Active accounts</div>
            <div class="value"><?= (int) $stats['active_users'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card"><div class="card-body">
            <div class="label">Roles</div>
            <div class="value"><?= (int) $stats['roles'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card"><div class="card-body">
            <div class="label">Audit events</div>
            <div class="value"><?= (int) $stats['audit'] ?></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card stat-card">
            <div class="card-body">
                <h2 class="h5">Operational snapshot</h2>
                <p class="text-secondary">Read-only counts from shared CRMS tables. Other members own those modules.</p>
                <div class="row text-center">
                    <div class="col-6 col-md-3"><div class="value fs-4"><?= (int) $ops['vehicles'] ?></div><div class="label">Vehicles</div></div>
                    <div class="col-6 col-md-3"><div class="value fs-4"><?= (int) $ops['customers'] ?></div><div class="label">Customers</div></div>
                    <div class="col-6 col-md-3"><div class="value fs-4"><?= (int) $ops['reservations'] ?></div><div class="label">Reservations</div></div>
                    <div class="col-6 col-md-3"><div class="value fs-4"><?= (int) $ops['rentals'] ?></div><div class="label">Rentals</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h2 class="h5">Recent activity</h2>
                <?php if (!hasPermission('audit.view')): ?>
                    <p class="text-secondary mb-0">Audit history is restricted to authorized roles.</p>
                <?php elseif (!$recent): ?>
                    <p class="text-secondary mb-0">No audit events yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($recent as $row): ?>
                            <li class="py-2 border-bottom">
                                <strong><?= e($row['action']) ?></strong>
                                <span class="badge text-bg-light"><?= e($row['result']) ?></span>
                                <div class="small text-secondary">
                                    <?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'System') ?>
                                    &middot; <?= e($row['created_at']) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

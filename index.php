<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Dashboard';
$pdo = getPDO();
$stats = [
    'vehicles'   => (int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE status != 'inactive'")->fetchColumn(),
    'available'  => (int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'")->fetchColumn(),
    'rented'     => (int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'rented'")->fetchColumn(),
    'categories' => (int)$pdo->query("SELECT COUNT(*) FROM vehicle_categories WHERE is_active = 1")->fetchColumn(),
];
require __DIR__ . '/includes/header.php';
?>
<div class="stats-grid">
    <div class="stat-card"><div class="num" style="color:var(--primary)"><?= $stats['vehicles'] ?></div><div class="label">Active Vehicles</div></div>
    <div class="stat-card"><div class="num" style="color:var(--success)"><?= $stats['available'] ?></div><div class="label">Available</div></div>
    <div class="stat-card"><div class="num" style="color:#1e40af"><?= $stats['rented'] ?></div><div class="label">Rented</div></div>
    <div class="stat-card"><div class="num" style="color:var(--warning)"><?= $stats['categories'] ?></div><div class="label">Categories</div></div>
</div>
<div class="card">
    <div class="card-header">Quick Actions</div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.6rem">
        <?php if (hasPermission('vehicles.manage')): ?>
            <a href="<?= BASE_URL ?>/vehicles/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Register Vehicle</a>
            <a href="<?= BASE_URL ?>/vehicles/categories.php" class="btn btn-outline"><i class="fas fa-tags"></i> Categories</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/vehicles/index.php" class="btn btn-outline"><i class="fas fa-car-side"></i> All Vehicles</a>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

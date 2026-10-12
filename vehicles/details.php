<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vehicle_status.php';
requireLogin();
if (!hasPermission('vehicles.view') && !hasPermission('vehicles.manage')) requirePermission('vehicles.view');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/vehicles/index.php');
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT v.*, vc.category_name
    FROM vehicles v JOIN vehicle_categories vc ON vc.category_id=v.category_id WHERE v.vehicle_id=?");
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) { setFlash('error','Vehicle not found.'); redirect('/vehicles/index.php'); }
$images = $pdo->prepare('SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY is_primary DESC, image_id');
$images->execute([$id]);
$images = $images->fetchAll();

// Coordinated operational status (rentals + maintenance + reservations)
$ops = computeOperationalStatus($id, $pdo);
$operational = $ops['status'] ?? $v['status'];

$pageTitle = $v['make'].' '.$v['model'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-car"></i> <?= e($v['make'].' '.$v['model']) ?> <span style="font-size:.95rem;font-weight:500;color:var(--gray-500)"><?= e($v['registration_no']) ?></span></h1>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <?php if (hasPermission('vehicles.manage')): ?>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>
<div class="detail-layout">
<div>
    <div class="card" style="margin-bottom:1rem"><div class="card-body">
    <?php if ($images): ?><div class="gallery"><?php foreach ($images as $img): ?>
        <img src="<?= BASE_URL ?>/uploads/vehicles/<?= e($img['image_path']) ?>" class="<?= $img['is_primary']?'primary':'' ?>">
    <?php endforeach; ?></div>
    <?php else: ?><div class="empty-state" style="padding:1.5rem"><i class="fas fa-image"></i><p>No images</p></div><?php endif; ?>
    </div></div>
    <?php if ($v['description']): ?>
    <div class="card"><div class="card-header">Description</div><div class="card-body"><p style="white-space:pre-wrap"><?= e($v['description']) ?></p></div></div>
    <?php endif; ?>
</div>
<div>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header">
            Operational Status <?= statusBadge($operational) ?>
        </div>
        <div class="card-body detail-meta">
            <dl>
                <dt>Recorded in DB</dt><dd><?= statusBadge($v['status']) ?></dd>
                <dt>Computed now</dt><dd><?= statusBadge($operational) ?></dd>
                <dt>Active rentals</dt><dd><?= (int)($ops['active_rentals'] ?? 0) ?></dd>
                <dt>Open maintenance</dt><dd><?= (int)($ops['open_maintenance'] ?? 0) ?></dd>
                <dt>Current reservations</dt><dd><?= (int)($ops['active_reservations'] ?? 0) ?></dd>
                <dt>Reason</dt><dd style="font-weight:500;color:var(--gray-700)"><?= e($ops['reason'] ?? '') ?></dd>
            </dl>
            <?php if (hasPermission('vehicles.manage')): ?>
            <div style="margin-top:.9rem;display:flex;flex-wrap:wrap;gap:.35rem">
                <a href="sync_status.php?id=<?= $id ?>" class="btn btn-primary btn-sm" title="Re-calculate from rentals & maintenance">
                    <i class="fas fa-sync-alt"></i> Sync Status
                </a>
                <?php if ($v['status'] !== 'available'): ?>
                <a href="deactivate.php?id=<?= $id ?>&status=available" class="btn btn-success btn-sm" data-confirm="Try set Available? (blocked if rental/maintenance active)"><i class="fas fa-check"></i> Available</a>
                <?php endif; ?>
                <?php if ($v['status'] !== 'maintenance'): ?>
                <a href="deactivate.php?id=<?= $id ?>&status=maintenance" class="btn btn-warning btn-sm" data-confirm="Put in maintenance?"><i class="fas fa-wrench"></i> Maintenance</a>
                <?php endif; ?>
                <?php if ($v['status'] !== 'inactive'): ?>
                <a href="deactivate.php?id=<?= $id ?>&status=inactive" class="btn btn-danger btn-sm" data-confirm="Deactivate vehicle? Status will stay inactive until you re-activate."><i class="fas fa-ban"></i> Deactivate</a>
                <?php endif; ?>
            </div>
            <p style="margin-top:.75rem;font-size:.8rem;color:var(--gray-500)">
                Status is coordinated with <strong>rentals</strong>, <strong>maintenance</strong>, and <strong>reservations</strong>.
                Use Sync after pickup/return or maintenance changes.
            </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header">Details</div>
        <div class="card-body detail-meta"><dl>
            <dt>Category</dt><dd><?= e($v['category_name']) ?></dd>
            <dt>Registration</dt><dd><?= e($v['registration_no']) ?></dd>
            <dt>VIN</dt><dd><?= e($v['vin']?:'—') ?></dd>
            <dt>Year</dt><dd><?= $v['manufacture_year']?:'—' ?></dd>
            <dt>Colour</dt><dd><?= e($v['colour']?:'—') ?></dd>
            <dt>Transmission</dt><dd><?= ucfirst($v['transmission']) ?></dd>
            <dt>Fuel</dt><dd><?= ucfirst($v['fuel_type']) ?></dd>
            <dt>Passengers</dt><dd><?= $v['passenger_capacity']?:'—' ?></dd>
            <dt>Mileage</dt><dd><?= number_format($v['current_mileage']) ?> km</dd>
        </dl></div>
    </div>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header">Rates</div>
        <div class="card-body detail-meta"><dl>
            <dt>Daily</dt><dd style="color:var(--primary);font-size:1.1rem"><?= formatMoney($v['daily_rate']) ?></dd>
            <dt>Weekly</dt><dd><?= $v['weekly_rate']!==null ? formatMoney($v['weekly_rate']) : '—' ?></dd>
            <dt>Monthly</dt><dd><?= $v['monthly_rate']!==null ? formatMoney($v['monthly_rate']) : '—' ?></dd>
        </dl></div>
    </div>
    <div class="card">
        <div class="card-header">Insurance & Inspection</div>
        <div class="card-body detail-meta"><dl>
            <dt>Policy</dt><dd><?= e($v['insurance_policy_no']?:'—') ?></dd>
            <dt>Insurance exp.</dt><dd><?= $v['insurance_expiry_date']?:'—' ?></dd>
            <dt>Inspection exp.</dt><dd><?= $v['inspection_expiry_date']?:'—' ?></dd>
        </dl></div>
    </div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

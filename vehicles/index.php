<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!hasPermission('vehicles.view') && !hasPermission('vehicles.manage')) requirePermission('vehicles.view');

$pageTitle = 'Vehicles';
$pdo = getPDO();

$search = trim($_GET['q'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';
$minRate = trim($_GET['min_rate'] ?? '');
$maxRate = trim($_GET['max_rate'] ?? '');
$view = $_GET['view'] ?? 'grid';

$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = '(v.registration_no LIKE ? OR v.make LIKE ? OR v.model LIKE ?)';
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($category > 0) { $where[] = 'v.category_id = ?'; $params[] = $category; }
if ($status !== '' && in_array($status, vehicleStatuses(), true)) { $where[] = 'v.status = ?'; $params[] = $status; }
if ($minRate !== '' && is_numeric($minRate)) { $where[] = 'v.daily_rate >= ?'; $params[] = $minRate; }
if ($maxRate !== '' && is_numeric($maxRate)) { $where[] = 'v.daily_rate <= ?'; $params[] = $maxRate; }

$sql = "SELECT v.*, vc.category_name,
        (SELECT image_path FROM vehicle_images vi WHERE vi.vehicle_id = v.vehicle_id ORDER BY vi.is_primary DESC, vi.image_id LIMIT 1) AS thumb
        FROM vehicles v JOIN vehicle_categories vc ON vc.category_id = v.category_id
        WHERE " . implode(' AND ', $where) . " ORDER BY v.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();
$categories = $pdo->query("SELECT category_id, category_name FROM vehicle_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-car-side"></i> Vehicles <span style="font-size:.85rem;font-weight:500;color:var(--gray-500)">(<?= count($vehicles) ?>)</span></h1>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <?php if (hasPermission('vehicles.manage')): ?>
        <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Register Vehicle</a>
        <a href="sync_status.php?all=1" class="btn btn-outline" data-confirm="Re-sync status for all active vehicles from rentals & maintenance?"><i class="fas fa-sync-alt"></i> Sync All Status</a>
        <?php endif; ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['view' => $view === 'grid' ? 'table' : 'grid'])) ?>" class="btn btn-outline">
            <i class="fas fa-<?= $view === 'grid' ? 'list' : 'th-large' ?>"></i> <?= $view === 'grid' ? 'Table' : 'Grid' ?>
        </a>
    </div>
</div>

<form method="get" class="filters">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="form-group"><label>Search</label><input type="text" name="q" class="form-control" placeholder="Reg, make, model…" value="<?= e($search) ?>"></div>
    <div class="form-group"><label>Category</label>
        <select name="category" class="form-control"><option value="">All</option>
        <?php foreach ($categories as $c): ?><option value="<?= $c['category_id'] ?>" <?= $category == $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>Status</label>
        <select name="status" class="form-control"><option value="">All</option>
        <?php foreach (vehicleStatuses() as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>Min rate</label><input type="number" name="min_rate" class="form-control" min="0" value="<?= e($minRate) ?>"></div>
    <div class="form-group"><label>Max rate</label><input type="number" name="max_rate" class="form-control" min="0" value="<?= e($maxRate) ?>"></div>
    <div class="form-group" style="flex:0"><button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button></div>
</form>

<?php if (empty($vehicles)): ?>
<div class="card"><div class="empty-state"><i class="fas fa-car"></i><h3>No vehicles found</h3><p>Register a vehicle or adjust filters.</p>
<?php if (hasPermission('vehicles.manage')): ?><a href="create.php" class="btn btn-primary" style="margin-top:.8rem"><i class="fas fa-plus"></i> Register</a><?php endif; ?>
</div></div>
<?php elseif ($view === 'table'): ?>
<div class="card"><div class="table-wrap"><table class="data-table">
<thead><tr><th>Registration</th><th>Make / Model</th><th>Category</th><th>Daily Rate</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($vehicles as $v): ?>
<tr>
    <td><strong><?= e($v['registration_no']) ?></strong></td>
    <td><?= e($v['make'] . ' ' . $v['model']) ?></td>
    <td><?= e($v['category_name']) ?></td>
    <td><?= formatMoney($v['daily_rate']) ?></td>
    <td><?= statusBadge($v['status']) ?></td>
    <td class="actions">
        <a href="details.php?id=<?= $v['vehicle_id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a>
        <?php if (hasPermission('vehicles.manage')): ?>
        <a href="edit.php?id=<?= $v['vehicle_id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></a>
        <a href="deactivate.php?id=<?= $v['vehicle_id'] ?>&status=<?= $v['status'] === 'inactive' ? 'available' : 'inactive' ?>"
           class="btn btn-<?= $v['status'] === 'inactive' ? 'success' : 'warning' ?> btn-sm"
           data-confirm="<?= $v['status'] === 'inactive' ? 'Re-activate this vehicle?' : 'Deactivate this vehicle?' ?>">
            <i class="fas fa-<?= $v['status'] === 'inactive' ? 'check' : 'ban' ?>"></i>
        </a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php else: ?>
<div class="vehicle-grid">
<?php foreach ($vehicles as $v): ?>
<div class="vehicle-card">
    <div class="thumb">
        <?php if ($v['thumb']): ?><img src="<?= BASE_URL ?>/uploads/vehicles/<?= e($v['thumb']) ?>" alt=""><?php else: ?><div class="placeholder"><i class="fas fa-car"></i></div><?php endif; ?>
        <?= statusBadge($v['status']) ?>
    </div>
    <div class="body">
        <div class="title"><?= e($v['make'] . ' ' . $v['model']) ?></div>
        <div class="meta"><?= e($v['registration_no']) ?> · <?= e($v['category_name']) ?></div>
        <div class="rate"><?= formatMoney($v['daily_rate']) ?> <small>/ day</small></div>
        <div class="card-actions">
            <a href="details.php?id=<?= $v['vehicle_id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a>
            <?php if (hasPermission('vehicles.manage')): ?>
            <a href="edit.php?id=<?= $v['vehicle_id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Edit</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

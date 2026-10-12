<?php
/**
 * Vehicle Categories – list, create, edit, activate, deactivate, delete
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission('vehicles.manage');
$pageTitle = 'Vehicle Categories';
$pdo = getPDO();
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$editData = null;

if ($editId > 0) {
    $s = $pdo->prepare('SELECT * FROM vehicle_categories WHERE category_id = ?');
    $s->execute([$editId]);
    $editData = $s->fetch();
}

/* ---------- Activate ---------- */
if (isset($_GET['activate'])) {
    $tid = (int)$_GET['activate'];
    $pdo->prepare('UPDATE vehicle_categories SET is_active = 1 WHERE category_id = ?')->execute([$tid]);
    setFlash('success', 'Category activated.');
    redirect('/vehicles/categories.php');
}

/* ---------- Deactivate ---------- */
if (isset($_GET['deactivate'])) {
    $tid = (int)$_GET['deactivate'];
    $pdo->prepare('UPDATE vehicle_categories SET is_active = 0 WHERE category_id = ?')->execute([$tid]);
    setFlash('success', 'Category deactivated. Existing vehicles are not affected.');
    redirect('/vehicles/categories.php');
}

/* ---------- Delete (only if no vehicles) ---------- */
if (isset($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM vehicles WHERE category_id = ?');
    $cnt->execute([$tid]);
    $vehicleCount = (int)$cnt->fetchColumn();

    if ($vehicleCount > 0) {
        setFlash('error', "Cannot delete: this category has $vehicleCount vehicle(s). Deactivate it instead, or reassign the vehicles first.");
        redirect('/vehicles/categories.php');
    }

    try {
        $pdo->prepare('DELETE FROM vehicle_categories WHERE category_id = ?')->execute([$tid]);
        setFlash('success', 'Category deleted permanently.');
    } catch (PDOException $e) {
        setFlash('error', 'Could not delete category. It may still be in use.');
    }
    redirect('/vehicles/categories.php');
}

/* ---------- Save (create or update) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['category_name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $daily   = trim($_POST['default_daily_rate'] ?? '');
    $weekly  = trim($_POST['default_weekly_rate'] ?? '');
    $monthly = trim($_POST['default_monthly_rate'] ?? '');
    $pax     = trim($_POST['passenger_capacity'] ?? '');
    $lug     = trim($_POST['luggage_capacity'] ?? '');
    $cid     = (int)($_POST['category_id'] ?? 0);

    if ($name === '') {
        $errors['category_name'] = 'Name required.';
    }
    if ($daily === '' || !is_numeric($daily) || $daily < 0) {
        $errors['default_daily_rate'] = 'Valid daily rate required.';
    }

    if (empty($errors)) {
        try {
            if ($cid > 0) {
                $pdo->prepare("
                    UPDATE vehicle_categories SET
                        category_name = ?, description = ?, default_daily_rate = ?,
                        default_weekly_rate = ?, default_monthly_rate = ?,
                        passenger_capacity = ?, luggage_capacity = ?
                    WHERE category_id = ?
                ")->execute([
                    $name,
                    $desc ?: null,
                    $daily,
                    $weekly !== '' ? $weekly : null,
                    $monthly !== '' ? $monthly : null,
                    $pax !== '' ? (int)$pax : null,
                    $lug !== '' ? (int)$lug : null,
                    $cid,
                ]);
                setFlash('success', 'Category updated.');
            } else {
                $pdo->prepare("
                    INSERT INTO vehicle_categories
                        (category_name, description, default_daily_rate, default_weekly_rate,
                         default_monthly_rate, passenger_capacity, luggage_capacity)
                    VALUES (?,?,?,?,?,?,?)
                ")->execute([
                    $name,
                    $desc ?: null,
                    $daily,
                    $weekly !== '' ? $weekly : null,
                    $monthly !== '' ? $monthly : null,
                    $pax !== '' ? (int)$pax : null,
                    $lug !== '' ? (int)$lug : null,
                ]);
                setFlash('success', 'Category created.');
            }
            redirect('/vehicles/categories.php');
        } catch (PDOException $e) {
            $errors['_general'] = ($e->getCode() == 23000)
                ? 'Category name already exists.'
                : $e->getMessage();
        }
    }
    if ($cid > 0) {
        $editId = $cid;
        $editData = [
            'category_id' => $cid,
            'category_name' => $name,
            'description' => $desc,
            'default_daily_rate' => $daily,
            'default_weekly_rate' => $weekly,
            'default_monthly_rate' => $monthly,
            'passenger_capacity' => $pax,
            'luggage_capacity' => $lug,
        ];
    }
}

$cats = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM vehicles v WHERE v.category_id = c.category_id) AS vehicle_count
    FROM vehicle_categories c
    ORDER BY c.category_name
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-tags"></i> Vehicle Categories</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem" class="cat-layout">

    <!-- List -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Daily Rate</th>
                        <th>Vehicles</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($cats)): ?>
                    <tr><td colspan="5" class="empty-state">No categories yet. Add one on the right.</td></tr>
                <?php else: foreach ($cats as $c): ?>
                    <tr>
                        <td>
                            <strong><?= e($c['category_name']) ?></strong>
                            <?php if ($c['description']): ?>
                                <br><small style="color:var(--gray-500)"><?= e($c['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= formatMoney($c['default_daily_rate']) ?></td>
                        <td><?= (int)$c['vehicle_count'] ?></td>
                        <td>
                            <span class="status <?= $c['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="actions">
                            <!-- Edit -->
                            <a href="?edit=<?= $c['category_id'] ?>" class="btn btn-outline btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>

                            <?php if ($c['is_active']): ?>
                                <!-- Deactivate -->
                                <a href="?deactivate=<?= $c['category_id'] ?>"
                                   class="btn btn-warning btn-sm"
                                   title="Deactivate"
                                   data-confirm="Deactivate this category? It will be hidden from new vehicle registration. Existing vehicles stay linked.">
                                    <i class="fas fa-ban"></i>
                                </a>
                            <?php else: ?>
                                <!-- Activate -->
                                <a href="?activate=<?= $c['category_id'] ?>"
                                   class="btn btn-success btn-sm"
                                   title="Activate"
                                   data-confirm="Activate this category?">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Delete -->
                            <a href="?delete=<?= $c['category_id'] ?>"
                               class="btn btn-danger btn-sm"
                               title="Delete permanently"
                               data-confirm="<?= (int)$c['vehicle_count'] > 0
                                   ? 'This category has ' . (int)$c['vehicle_count'] . ' vehicle(s). Delete will be blocked — deactivate instead?'
                                   : 'Permanently delete this category? This cannot be undone.' ?>">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form -->
    <div class="card">
        <div class="card-header"><?= $editData ? 'Edit Category' : 'Add Category' ?></div>
        <div class="card-body">
            <?php if (!empty($errors['_general'])): ?>
                <div class="alert alert-error"><?= e($errors['_general']) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="category_id" value="<?= (int)($editData['category_id'] ?? 0) ?>">

                <div class="form-group">
                    <label>Name <span class="required">*</span></label>
                    <input type="text" name="category_name" class="form-control <?= isset($errors['category_name']) ? 'is-invalid' : '' ?>"
                           required maxlength="80"
                           value="<?= e($editData['category_name'] ?? ($_POST['category_name'] ?? '')) ?>">
                    <?php if (isset($errors['category_name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['category_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= e($editData['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Daily Rate (RWF) <span class="required">*</span></label>
                    <input type="number" name="default_daily_rate" step="0.01" min="0"
                           class="form-control <?= isset($errors['default_daily_rate']) ? 'is-invalid' : '' ?>" required
                           value="<?= e($editData['default_daily_rate'] ?? ($_POST['default_daily_rate'] ?? '')) ?>">
                    <?php if (isset($errors['default_daily_rate'])): ?>
                        <div class="invalid-feedback"><?= e($errors['default_daily_rate']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Weekly Rate</label>
                    <input type="number" name="default_weekly_rate" step="0.01" min="0" class="form-control"
                           value="<?= e($editData['default_weekly_rate'] ?? ($_POST['default_weekly_rate'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label>Monthly Rate</label>
                    <input type="number" name="default_monthly_rate" step="0.01" min="0" class="form-control"
                           value="<?= e($editData['default_monthly_rate'] ?? ($_POST['default_monthly_rate'] ?? '')) ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Passengers</label>
                        <input type="number" name="passenger_capacity" class="form-control"
                               value="<?= e($editData['passenger_capacity'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Luggage</label>
                        <input type="number" name="luggage_capacity" class="form-control"
                               value="<?= e($editData['luggage_capacity'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $editData ? 'Update' : 'Save' ?>
                    </button>
                    <?php if ($editData): ?>
                        <a href="categories.php" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<style>@media(max-width:800px){.cat-layout{grid-template-columns:1fr!important}}</style>
<?php require __DIR__ . '/../includes/footer.php'; ?>

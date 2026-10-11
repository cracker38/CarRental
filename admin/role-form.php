<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$actor = requirePermission('roles.manage');

$id = (int) ($_GET['id'] ?? $_POST['role_id'] ?? 0);
$role = RoleService::find($id);
if (!$role) {
    flash_set('danger', 'Role was not found.');
    redirect('admin/roles.php');
}

$allPermissions = RoleService::permissions();
$assigned = RoleService::permissionIdsForRole($id);
$locked = $role['role_name'] === 'System Administrator';
$grouped = [];
foreach ($allPermissions as $permission) {
    $grouped[permission_group($permission['permission_key'])][] = $permission;
}

if (is_post()) {
    Csrf::requireValid();
    $ids = array_map('intval', $_POST['permissions'] ?? []);
    $result = RoleService::savePermissions($id, $ids, $actor);
    flash_set($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Permissions updated.' : ($result['error'] ?? 'Unable to save permissions.'));
    redirect('admin/role-form.php?id=' . $id);
}

$pageTitle = 'Role permissions';
$currentPage = 'roles';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumb"><a href="<?= e(base_url('admin/roles.php')) ?>">Roles</a> / Permissions</div>
        <h1 class="h3 mb-1"><?= e($role['role_name']) ?></h1>
        <p class="text-secondary mb-0"><?= e((string) $role['description']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/roles.php')) ?>">Back</a>
</div>

<div class="card crms-card">
    <div class="card-body">
        <?php if ($locked): ?>
            <div class="alert alert-warning">System Administrator always has every permission. This list is read-only so the team cannot be locked out.</div>
        <?php else: ?>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="input-icon">
                        <i class="bi bi-search"></i>
                        <input class="form-control" id="permissionSearch" placeholder="Search permissions">
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="role_id" value="<?= (int) $id ?>">
            <?php foreach ($grouped as $group => $items): ?>
                <div class="perm-group">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3><?= e($group) ?></h3>
                        <?php if (!$locked): ?>
                            <button class="btn btn-sm btn-link" type="button" data-check-group="<?= e($group) ?>">Toggle group</button>
                        <?php endif; ?>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($items as $permission): ?>
                            <div class="col-md-6" data-perm-item>
                                <div class="perm-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= (int) $permission['permission_id'] ?>"
                                            id="p<?= (int) $permission['permission_id'] ?>"
                                            data-group="<?= e($group) ?>"
                                            <?= ($locked || in_array((int) $permission['permission_id'], $assigned, true)) ? 'checked' : '' ?>
                                            <?= $locked ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="p<?= (int) $permission['permission_id'] ?>">
                                            <code><?= e($permission['permission_key']) ?></code>
                                            <div class="small text-secondary"><?= e((string) $permission['description']) ?></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$locked): ?>
                <button class="btn btn-primary mt-2" type="submit">Save permissions</button>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

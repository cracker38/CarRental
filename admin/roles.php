<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$actor = requirePermission('roles.manage');

if (is_post()) {
    Csrf::requireValid();
    $result = RoleService::create((string) ($_POST['role_name'] ?? ''), (string) ($_POST['description'] ?? ''), $actor);
    flash_set($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Role created.' : ($result['error'] ?? 'Unable to create the role.'));
    if (!empty($result['id'])) {
        redirect('admin/role-form.php?id=' . (int) $result['id']);
    }
    redirect('admin/roles.php');
}

$roles = RoleService::all();
$pageTitle = 'Roles';
$currentPage = 'roles';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Roles and permissions</h1>
        <p class="text-secondary mb-0">Built-in business roles are preserved. Custom roles can be added without changing the schema.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card stat-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Role</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><strong><?= e($role['role_name']) ?></strong></td>
                            <td><?= ((int) $role['is_system_role'] === 1) ? 'System' : 'Custom' ?></td>
                            <td><?= e((string) $role['description']) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/role-form.php?id=' . (int) $role['role_id'])) ?>">Permissions</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card stat-card">
            <div class="card-body">
                <h2 class="h5">Create custom role</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="role_name" required maxlength="60">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" maxlength="255"></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Create role</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

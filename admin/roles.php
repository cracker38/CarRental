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
$counts = RoleService::userCounts();
$pageTitle = 'Roles';
$currentPage = 'roles';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumb">Administration</div>
        <h1 class="h3 mb-1">Roles and permissions</h1>
        <p class="text-secondary mb-0">Built-in business roles stay intact. Custom roles can be added without changing the schema.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card crms-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Role</th>
                        <th>Type</th>
                        <th>Users</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td>
                                <strong><?= e($role['role_name']) ?></strong>
                                <div class="small text-secondary"><?= e((string) $role['description']) ?></div>
                            </td>
                            <td><?= ((int) $role['is_system_role'] === 1) ? '<span class="badge text-bg-light">System</span>' : '<span class="badge text-bg-light">Custom</span>' ?></td>
                            <td><?= (int) ($counts[(int) $role['role_id']] ?? 0) ?></td>
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
        <div class="card crms-card">
            <div class="card-body">
                <h2 class="h5">Create custom role</h2>
                <p class="text-secondary small">Use a clear name, then assign only the permissions this job needs.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="role_name" required maxlength="60" placeholder="e.g. Branch Supervisor">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" maxlength="255" placeholder="What this role is allowed to do"></textarea>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Create role</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

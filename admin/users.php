<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$actor = requirePermission('users.manage');

if (is_post()) {
    Csrf::requireValid();
    $id = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'activate') {
        $result = UserService::setStatus($id, 'active', $actor);
    } elseif ($action === 'deactivate') {
        $result = UserService::setStatus($id, 'inactive', $actor);
    } elseif ($action === 'suspend') {
        $result = UserService::setStatus($id, 'suspended', $actor);
    } else {
        $result = ['ok' => false, 'error' => 'Unknown action.'];
    }
    flash_set($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Account status updated.' : ($result['error'] ?? 'Unable to update the account.'));
    redirect('admin/users.php');
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'role_id' => (string) ($_GET['role_id'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
];
$page = (int) ($_GET['page'] ?? 1);
$result = UserService::paginate($filters, $page);
$roles = RoleService::all();
$pageTitle = 'Users';
$currentPage = 'users';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Users</h1>
        <p class="text-secondary mb-0">Create, search, and control staff accounts.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('admin/user-form.php')) ?>">New user</a>
</div>

<div class="card stat-card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, email, or phone">
            </div>
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select class="form-select" name="role_id">
                    <option value="">All roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['role_id'] ?>" <?= $filters['role_id'] === (string) $role['role_id'] ? 'selected' : '' ?>>
                            <?= e($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['active', 'inactive', 'suspended', 'pending'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last login</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$result['rows']): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">No users match the current filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($result['rows'] as $row): ?>
                <tr>
                    <td>
                        <strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                        <div class="small text-secondary">Created <?= e($row['created_at']) ?></div>
                    </td>
                    <td><?= e($row['email']) ?></td>
                    <td><?= e($row['role_name']) ?></td>
                    <td><span class="badge badge-status-<?= e($row['account_status']) ?>"><?= e($row['account_status']) ?></span></td>
                    <td><?= e($row['last_login_at'] ?: 'Never') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/user-form.php?id=' . (int) $row['user_id'])) ?>">Edit</a>
                        <form class="d-inline" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                            <?php if ($row['account_status'] === 'active'): ?>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="deactivate" data-confirm="Deactivate this account?">Deactivate</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-success" name="action" value="activate">Activate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pager = $result['pager']; if ($pager['pages'] > 1): ?>
        <div class="card-body d-flex justify-content-between">
            <div class="text-secondary small">Showing page <?= (int) $pager['page'] ?> of <?= (int) $pager['pages'] ?></div>
            <div class="btn-group">
                <?php if ($pager['page'] > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(page_url($pager, $pager['page'] - 1)) ?>">Previous</a><?php endif; ?>
                <?php if ($pager['page'] < $pager['pages']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(page_url($pager, $pager['page'] + 1)) ?>">Next</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

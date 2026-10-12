<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$actor = requirePermission('users.manage');

$id = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);
$existing = $id ? UserService::find($id) : null;
if ($id && !$existing) {
    flash_set('danger', 'User was not found.');
    redirect('admin/users.php');
}

$roles = RoleService::all();
$errors = [];
$values = $existing ?: [
    'first_name' => old('first_name'),
    'last_name' => old('last_name'),
    'email' => old('email'),
    'phone' => old('phone'),
    'role_id' => old('role_id'),
    'account_status' => old('account_status', 'active'),
    'must_change_password' => old('must_change_password', '1'),
];

if (is_post()) {
    Csrf::requireValid();
    $action = (string) ($_POST['form_action'] ?? 'save');
    if ($action === 'reset_password' && $existing) {
        $result = UserService::adminResetPassword($id, (string) ($_POST['new_password'] ?? ''), $actor);
        flash_set($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Password was reset. The user must change it at next sign-in.' : ($result['error'] ?? 'Reset failed.'));
        redirect('admin/user-form.php?id=' . $id);
    }

    $payload = [
        'first_name' => (string) ($_POST['first_name'] ?? ''),
        'last_name' => (string) ($_POST['last_name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'role_id' => (string) ($_POST['role_id'] ?? ''),
        'account_status' => (string) ($_POST['account_status'] ?? 'active'),
        'must_change_password' => isset($_POST['must_change_password']) ? '1' : '0',
        'password' => (string) ($_POST['password'] ?? ''),
    ];
    $result = $existing ? UserService::update($id, $payload, $actor) : UserService::create($payload, $actor);
    if ($result['ok']) {
        clear_old();
        flash_set('success', $existing ? 'User updated.' : 'User created.');
        redirect('admin/users.php');
    }
    $errors = $result['errors'] ?? ['form' => 'Unable to save the user.'];
    remember_old($payload);
    $values = $payload;
}

$pageTitle = $existing ? 'Edit user' : 'New user';
$currentPage = 'users';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumb"><a href="<?= e(base_url('admin/users.php')) ?>">Users</a> / <?= $existing ? 'Edit' : 'New' ?></div>
        <h1 class="h3 mb-1"><?= e($pageTitle) ?></h1>
        <?php if ($existing): ?>
            <p class="text-secondary mb-0">Created <?= e(format_datetime($existing['created_at'])) ?> · Updated <?= e(format_datetime($existing['updated_at'])) ?></p>
        <?php else: ?>
            <p class="text-secondary mb-0">Create a staff account and assign a least-privilege role.</p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/users.php')) ?>">Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card crms-card">
            <div class="card-body">
                <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
                <form method="post" action="<?= e(base_url('admin/user-form.php' . ($existing ? '?id=' . (int) $id : ''))) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="save">
                    <?php if ($existing): ?>
                        <input type="hidden" name="user_id" value="<?= (int) $id ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First name</label>
                            <input class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" name="first_name" value="<?= e((string) $values['first_name']) ?>" required autofocus>
                            <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last name</label>
                            <input class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" name="last_name" value="<?= e((string) $values['last_name']) ?>" required>
                            <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" name="email" value="<?= e((string) $values['email']) ?>" required>
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" value="<?= e((string) ($values['phone'] ?? '')) ?>">
                            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select <?= isset($errors['role_id']) ? 'is-invalid' : '' ?>" name="role_id" required>
                                <option value="">Select role</option>
                                <?php foreach ($roles as $role): ?>
                                    <?php if (!Authorization::canAssignRole($actor, $role) && (int) ($values['role_id'] ?? 0) !== (int) $role['role_id']) { continue; } ?>
                                    <option value="<?= (int) $role['role_id'] ?>" <?= (string) ($values['role_id'] ?? '') === (string) $role['role_id'] ? 'selected' : '' ?>>
                                        <?= e($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['role_id'])): ?><div class="invalid-feedback"><?= e($errors['role_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select <?= isset($errors['account_status']) ? 'is-invalid' : '' ?>" name="account_status">
                                <?php foreach (['active', 'inactive', 'suspended', 'pending'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= ($values['account_status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['account_status'])): ?><div class="invalid-feedback"><?= e($errors['account_status']) ?></div><?php endif; ?>
                        </div>
                        <?php if (!$existing): ?>
                            <div class="col-12">
                                <label class="form-label">Temporary password</label>
                                <div class="input-icon">
                                    <i class="bi bi-lock"></i>
                                    <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="newUserPassword" name="password" required>
                                    <button class="password-toggle" type="button" data-password-toggle="newUserPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                </div>
                                <div class="form-text">At least 10 characters, with uppercase, lowercase, and a number.</div>
                                <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="must_change_password" id="mcp" <?= !empty($values['must_change_password']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mcp">Require password change at next sign-in</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary" type="submit">Save user</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php if ($existing): ?>
        <div class="col-lg-4">
            <div class="card crms-card">
                <div class="card-body">
                    <h2 class="h5">Reset password</h2>
                    <p class="text-secondary">Set a new temporary password. The plaintext value is not stored or logged.</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="form_action" value="reset_password">
                        <div class="mb-3">
                            <label class="form-label">Temporary password</label>
                            <input class="form-control" type="password" name="new_password" required minlength="10">
                        </div>
                        <button class="btn btn-outline-primary" type="submit" data-confirm="Reset this user's password?">Reset password</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

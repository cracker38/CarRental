<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireLogin();

if (is_post()) {
    Csrf::requireValid();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if ($new !== $confirm) {
        flash_set('danger', 'Password confirmation does not match.');
    } else {
        $result = UserService::changeOwnPassword($user, $current, $new);
        if ($result['ok']) {
            flash_set('success', 'Your password was updated.');
            redirect('admin/dashboard.php');
        }
        flash_set('danger', $result['error'] ?? 'Unable to change password.');
    }
}

$pageTitle = 'Change password';
$currentPage = '';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumb">Account</div>
        <h1 class="h3 mb-0">Change password</h1>
    </div>
</div>
<div class="card crms-card" style="max-width: 520px;">
    <div class="card-body">
        <p class="text-secondary">Choose a strong password that you have not used here before.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Current password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input class="form-control" type="password" id="current_password" name="current_password" required>
                    <button class="password-toggle" type="button" data-password-toggle="current_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">New password</label>
                <div class="input-icon">
                    <i class="bi bi-shield-lock"></i>
                    <input class="form-control" type="password" id="new_password" name="new_password" required minlength="10">
                    <button class="password-toggle" type="button" data-password-toggle="new_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
                <div class="form-text">At least 10 characters, with uppercase, lowercase, and a number.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm new password</label>
                <input class="form-control" type="password" name="confirm_password" required>
            </div>
            <button class="btn btn-primary" type="submit">Update password</button>
        </form>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

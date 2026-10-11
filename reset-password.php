<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '') {
    flash_set('danger', 'This reset link is invalid or has expired.');
    redirect('forgot-password.php');
}

if (is_post()) {
    Csrf::requireValid();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if ($password !== $confirm) {
        flash_set('danger', 'Password confirmation does not match.');
    } else {
        $result = PasswordReset::reset($token, $password);
        if ($result['ok']) {
            flash_set('success', 'Your password was updated. You can now sign in.');
            redirect('login.php');
        }
        flash_set('danger', $result['error']);
    }
}

$pageTitle = 'Choose a new password';
ob_start();
?>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="10">
        <div class="form-text">At least 10 characters, with uppercase, lowercase, and a number.</div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password_confirm">Confirm password</label>
        <input class="form-control" type="password" id="password_confirm" name="password_confirm" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Update password</button>
</form>
<?php
$authContent = ob_get_clean();
require BASE_PATH . '/includes/auth_layout.php';

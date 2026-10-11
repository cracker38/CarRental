<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

if (is_post()) {
    Csrf::requireValid();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    PasswordReset::request($email);
    flash_set('success', 'If an account exists for that email, password reset instructions have been prepared.');
    redirect('forgot-password.php');
}

$pageTitle = 'Reset password';
ob_start();
?>
<p class="text-secondary">Enter the email on your staff account. For security, we show the same message whether or not the account exists.</p>
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">Work email</label>
        <div class="input-icon">
            <i class="bi bi-envelope"></i>
            <input class="form-control" type="email" id="email" name="email" required placeholder="you@company.com">
        </div>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit">Send reset instructions</button>
    <div class="text-center mt-3">
        <a href="<?= e(base_url('login.php')) ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a>
    </div>
</form>
<?php if (config('debug')): ?>
    <p class="small text-secondary mt-4 mb-0">Development mode: reset links are written to a protected storage file, not shown on this page.</p>
<?php endif; ?>
<?php
$authContent = ob_get_clean();
require BASE_PATH . '/includes/auth_layout.php';

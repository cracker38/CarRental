<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$errors = [];
$email = '';
if (is_post()) {
    Csrf::requireValid();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $result = Auth::attempt($email, $password);
    if ($result['ok']) {
        $intended = $_SESSION['_intended'] ?? 'admin/dashboard.php';
        unset($_SESSION['_intended']);
        if (is_string($intended) && str_starts_with($intended, '/')) {
            header('Location: ' . $intended);
            exit;
        }
        redirect('admin/dashboard.php');
    }
    $errors['form'] = $result['error'];
}

$pageTitle = 'Sign in';
ob_start();
?>
<form method="post" novalidate data-remember-email="true">
    <?= csrf_field() ?>
    <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-danger"><?= e($errors['form']) ?></div>
    <?php endif; ?>
    <div class="mb-3">
        <label class="form-label" for="email">Work email</label>
        <div class="input-icon">
            <i class="bi bi-envelope"></i>
            <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" placeholder="you@company.com">
        </div>
    </div>
    <div class="mb-3">
        <div class="d-flex justify-content-between">
            <label class="form-label" for="password">Password</label>
            <a class="small" href="<?= e(base_url('forgot-password.php')) ?>">Forgot password?</a>
        </div>
        <div class="input-icon">
            <i class="bi bi-lock"></i>
            <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit">Sign in to CRMS</button>
</form>
<?php
$authContent = ob_get_clean();
require BASE_PATH . '/includes/auth_layout.php';

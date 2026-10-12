<?php
/**
 * Create a Business Manager account (the "Manager" role for vehicle/image management).
 * Use this instead of System Administrator for day-to-day fleet work.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getPDO();
$message = $error = '';

$mgrRole = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Business Manager' LIMIT 1")->fetch();
if (!$mgrRole) {
    $error = 'Business Manager role not found. Import the database schema first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if ($first === '' || $last === '' || $email === '' || $pass === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ")->execute([$mgrRole['role_id'], $first, $last, $email, $hash]);
            $message = 'Manager account created. You can now <a href="login.php">login</a> and manage vehicles &amp; images.';
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? 'An account with this email already exists.' : $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Manager | Car Rental</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--gray-50)}
.setup-card{width:100%;max-width:420px;background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);padding:2rem}
</style>
</head>
<body>
<div class="setup-card">
    <h1 style="font-size:1.25rem;margin-bottom:.4rem">
        <i class="fas fa-user-tie" style="color:var(--primary)"></i> Create Manager Account
    </h1>
    <p style="color:var(--gray-500);font-size:.85rem;margin-bottom:1.2rem">
        Creates a <strong>Business Manager</strong> user who can register vehicles and edit images.
        System Administrator cannot edit vehicle images.
    </p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$message): ?>
    <form method="post">
        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" required value="<?= e($_POST['first_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" required value="<?= e($_POST['last_name'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password (min 8 chars)</label>
            <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="password_confirm" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
            <i class="fas fa-user-plus"></i> Create Manager
        </button>
    </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:1rem;font-size:.85rem">
        <a href="login.php">Back to Login</a>
        &nbsp;·&nbsp;
        <a href="setup_admin.php">Create System Admin instead</a>
    </p>
</div>
</body>
</html>

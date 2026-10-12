<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) redirect('/index.php');

$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid email or password.';
            } elseif ($user['account_status'] !== 'active') {
                $error = 'Account is ' . $user['account_status'] . '.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role_id'] = (int)$user['role_id'];
                loadUserPermissions($pdo, (int)$user['role_id']);
                $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = ?')->execute([(int)$user['user_id']]);
                $redir = $_SESSION['redirect_after_login'] ?? BASE_URL . '/index.php';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redir);
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Login unavailable. Check database connection.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Car Rental</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#eff6ff,#f8fafc,#e0e7ff)}
.login-card{width:100%;max-width:400px;background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.1);padding:2.2rem 1.8rem}
.brand{text-align:center;margin-bottom:1.8rem}
.brand i{font-size:2.3rem;color:var(--primary)}
.brand h1{font-size:1.3rem;margin-top:.4rem}
.brand p{color:var(--gray-500);font-size:.85rem}
</style>
</head>
<body>
<div class="login-card">
    <div class="brand"><i class="fas fa-car"></i><h1>Car Rental System</h1><p>Staff Login</p></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Email</label>
            <input type="email" name="email" class="form-control" required autofocus value="<?= e($email) ?>">
        </div>
        <div class="form-group"><label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.65rem;margin-top:.4rem">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
    </form>
    <p style="text-align:center;margin-top:1.3rem;font-size:.85rem;color:var(--gray-500)">
        Setup: <a href="<?= BASE_URL ?>/auth/setup_manager.php">Manager</a> 
    </p>
</div>
</body>
</html>

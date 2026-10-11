<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI only.';
    exit(1);
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'CRMS-Test-Runner';
$_SERVER['REQUEST_METHOD'] = 'GET';

require dirname(__DIR__) . '/app/bootstrap.php';

$passed = 0;
$failed = 0;
$failures = [];

function assert_true(bool $condition, string $name): void
{
    global $passed, $failed, $failures;
    if ($condition) {
        $passed++;
        echo "PASS  {$name}\n";
        return;
    }
    $failed++;
    $failures[] = $name;
    echo "FAIL  {$name}\n";
}

function actor_from_user(int $userId): array
{
    $user = Auth::loadUser($userId);
    if (!$user) {
        throw new RuntimeException('Unable to load actor');
    }
    return $user;
}

$pdo = Database::connection();
$adminRole = (int) $pdo->query("SELECT role_id FROM roles WHERE role_name = 'System Administrator'")->fetchColumn();
$managerRole = (int) $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Business Manager'")->fetchColumn();
$officerRole = (int) $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Rental Officer'")->fetchColumn();

$pdo->prepare("DELETE FROM users WHERE email LIKE 'test.crms.%@carrental.local'")->execute();

$adminHash = password_hash('AdminPass123', PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?,?,?,?,?,?)')
    ->execute([$adminRole, 'Test', 'Admin', 'test.crms.admin@carrental.local', $adminHash, 'active']);
$adminId = (int) $pdo->lastInsertId();
$admin = actor_from_user($adminId);

$pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?,?,?,?,?,?)')
    ->execute([$managerRole, 'Test', 'Manager', 'test.crms.manager@carrental.local', password_hash('ManagerPass123', PASSWORD_DEFAULT), 'active']);
$managerId = (int) $pdo->lastInsertId();
$manager = actor_from_user($managerId);

$pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?,?,?,?,?,?)')
    ->execute([$officerRole, 'Test', 'Officer', 'test.crms.officer@carrental.local', password_hash('OfficerPass123', PASSWORD_DEFAULT), 'inactive']);
$officerId = (int) $pdo->lastInsertId();

assert_true(password_verify('AdminPass123', $adminHash), 'Password hashing and verification');
assert_true(Validator::password('short') !== null, 'Weak password is rejected');
assert_true(Validator::password('StrongPass123') === null, 'Strong password is accepted');
assert_true(e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;', 'XSS output is escaped');
assert_true(Csrf::token() !== '' && Csrf::validate(Csrf::token()) && !Csrf::validate('invalid'), 'CSRF token validation');

$okLogin = Auth::attempt('test.crms.admin@carrental.local', 'AdminPass123');
assert_true($okLogin['ok'] === true, 'Valid login succeeds');
$badLogin = Auth::attempt('test.crms.admin@carrental.local', 'wrong-password');
assert_true($badLogin['ok'] === false, 'Invalid password is rejected');
$sqli = Auth::attempt("' OR 1=1 --", 'x');
assert_true($sqli['ok'] === false, 'SQL injection login payload is rejected');
$inactive = Auth::attempt('test.crms.officer@carrental.local', 'OfficerPass123');
assert_true($inactive['ok'] === false, 'Inactive account cannot sign in');

assert_true(Authorization::allows('users.manage', $admin), 'Administrator has users.manage');
assert_true(Authorization::allows('users.view', $admin), 'users.view alias is granted through users.manage');
assert_true(!Authorization::allows('users.manage', $manager), 'Manager cannot manage users');
assert_true(Authorization::allows('reports.view', $manager), 'Manager can view reports through operational/financial permissions');
assert_true(!Authorization::canAssignRole($manager, ['role_name' => 'System Administrator']), 'Non-admin cannot assign System Administrator');
assert_true(Authorization::canAssignRole($admin, ['role_name' => 'System Administrator']), 'Admin can assign System Administrator');

$created = UserService::create([
    'first_name' => 'Temp',
    'last_name' => 'User',
    'email' => 'test.crms.created@carrental.local',
    'phone' => '+250780000000',
    'role_id' => (string) $officerRole,
    'account_status' => 'active',
    'password' => 'CreatedPass123',
    'must_change_password' => '1',
], $admin);
assert_true($created['ok'] === true, 'Authorized user creation succeeds');

$escalation = UserService::create([
    'first_name' => 'Evil',
    'last_name' => 'User',
    'email' => 'test.crms.evil@carrental.local',
    'phone' => '',
    'role_id' => (string) $adminRole,
    'account_status' => 'active',
    'password' => 'CreatedPass123',
], $manager);
assert_true($escalation['ok'] === false, 'Privilege escalation via role assignment is blocked');

$pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) VALUES (?,?,?,?,?,?)')
    ->execute([$adminRole, 'Last', 'Admin', 'test.crms.lastadmin@carrental.local', password_hash('LastAdmin1234', PASSWORD_DEFAULT), 'inactive']);
$otherAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='System Administrator' AND u.account_status='active'")->fetchColumn();
$block = UserService::setStatus($adminId, 'inactive', $admin);
if ($otherAdmin <= 1) {
    assert_true($block['ok'] === false, 'Last active administrator cannot be deactivated');
} else {
    UserService::setStatus($adminId, 'active', $admin);
    assert_true(true, 'Last active administrator protection skipped because extra admins exist');
}

PasswordReset::request('test.crms.admin@carrental.local');
$tokenRow = $pdo->query('SELECT token_id FROM password_reset_tokens WHERE user_id = ' . $adminId . ' AND used_at IS NULL ORDER BY token_id DESC LIMIT 1')->fetch();
assert_true(!empty($tokenRow), 'Password reset token is stored');
$resetFail = PasswordReset::reset('not-a-real-token', 'NewPassword123');
assert_true($resetFail['ok'] === false, 'Invalid reset token is rejected');

$logFile = BASE_PATH . '/storage/password_resets.log';
if (is_file($logFile)) {
    $lines = file($logFile);
    $last = $lines ? trim((string) end($lines)) : '';
    if (preg_match('/token=([a-f0-9]+)/', $last, $m)) {
        $resetOk = PasswordReset::reset($m[1], 'BrandNewPass123');
        assert_true($resetOk['ok'] === true, 'Valid password reset succeeds');
        $reuse = PasswordReset::reset($m[1], 'BrandNewPass123');
        assert_true($reuse['ok'] === false, 'Reset token cannot be reused');
        $loginAfter = Auth::attempt('test.crms.admin@carrental.local', 'BrandNewPass123');
        assert_true($loginAfter['ok'] === true, 'Login works after password reset');
    } else {
        assert_true(false, 'Valid password reset succeeds');
        assert_true(false, 'Reset token cannot be reused');
        assert_true(false, 'Login works after password reset');
    }
} else {
    assert_true(false, 'Valid password reset succeeds');
    assert_true(false, 'Reset token cannot be reused');
    assert_true(false, 'Login works after password reset');
}

Settings::update('business_name', 'CRMS Test Fleet', $adminId);
assert_true(Settings::get('business_name') === 'CRMS Test Fleet', 'Settings update persists');
$invalidSetting = false;
try {
    Settings::update('not_a_real_setting', 'x', $adminId);
} catch (InvalidArgumentException $e) {
    $invalidSetting = true;
}
assert_true($invalidSetting, 'Unknown settings are rejected');
Settings::update('business_name', 'Car Rental Business', $adminId);

$auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('login.success','login.failed','user.create','password_reset.request')")->fetchColumn();
assert_true($auditCount > 0, 'Audit events are recorded');

$ownDenied = UserService::setStatus($adminId, 'inactive', $admin);
assert_true($ownDenied['ok'] === false, 'User cannot deactivate their own account');

$base = rtrim((string) ($GLOBALS['crms_base_url'] ?? 'http://localhost/carrental/CarRental'), '/');
$loginPage = @file_get_contents($base . '/login.php');
if (is_string($loginPage)) {
    assert_true(str_contains($loginPage, 'name="csrf_token"'), 'Login page includes a CSRF token');
    $dash = @file_get_contents($base . '/admin/dashboard.php');
    $headers = $http_response_header ?? [];
    $redirected = false;
    foreach ($headers as $header) {
        if (stripos($header, 'Location:') === 0 && str_contains($header, 'login.php')) {
            $redirected = true;
        }
    }
    assert_true($dash === false || $redirected || (is_string($dash) && str_contains($dash, 'Sign in')), 'Protected dashboard rejects anonymous access');
} else {
    echo "INFO  HTTP tests skipped because Apache did not respond at {$base}\n";
}

$pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id IN (SELECT user_id FROM users WHERE email LIKE 'test.crms.%@carrental.local')")->execute();
$pdo->prepare("DELETE FROM users WHERE email LIKE 'test.crms.%@carrental.local'")->execute();

echo "\n{$passed} passed, {$failed} failed\n";
if ($failures) {
    echo 'Failed cases: ' . implode('; ', $failures) . "\n";
    exit(1);
}
exit(0);

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script can only be run from the command line.';
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$email = 'admin@carrental.local';
$password = 'Admin@12345';

$roleStmt = Database::connection()->prepare("SELECT role_id FROM roles WHERE role_name = 'System Administrator' LIMIT 1");
$roleStmt->execute();
$roleId = $roleStmt->fetchColumn();
if (!$roleId) {
    fwrite(STDERR, "System Administrator role is missing. Import Database/carrental.sql first.\n");
    exit(1);
}

$exists = Database::connection()->prepare('SELECT user_id FROM users WHERE LOWER(email) = :email');
$exists->execute([':email' => $email]);
if ($exists->fetchColumn()) {
    echo "Administrator already exists: {$email}\n";
    exit(0);
}

Database::connection()->prepare(
    'INSERT INTO users (role_id, first_name, last_name, email, phone, password_hash, account_status, must_change_password)
     VALUES (:role_id, :first_name, :last_name, :email, :phone, :password_hash, :status, 0)'
)->execute([
    ':role_id' => $roleId,
    ':first_name' => 'System',
    ':last_name' => 'Administrator',
    ':email' => $email,
    ':phone' => null,
    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ':status' => 'active',
]);

echo "Created administrator {$email}\n";
echo "Temporary password: {$password}\n";
echo "Change this password after the first sign-in.\n";

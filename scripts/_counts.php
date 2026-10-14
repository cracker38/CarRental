<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=carrental', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['customers', 'vehicles', 'users', 'roles'] as $t) {
    echo "$t: " . $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . "\n";
}
echo "\nUsers:\n";
foreach ($pdo->query('SELECT user_id, email, account_status, role_id FROM users') as $u) {
    echo json_encode($u) . "\n";
}
echo "\nVehicles:\n";
foreach ($pdo->query('SELECT vehicle_id, registration_no, status, current_mileage FROM vehicles LIMIT 5') as $v) {
    echo json_encode($v) . "\n";
}
echo "\nCustomers:\n";
foreach ($pdo->query('SELECT customer_id, first_name, last_name FROM customers LIMIT 5') as $c) {
    echo json_encode($c) . "\n";
}

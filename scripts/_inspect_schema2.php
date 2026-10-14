<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=carrental;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "=== vw_invoice_balances definition ===\n";
$row = $pdo->query("SHOW CREATE VIEW vw_invoice_balances")->fetch(PDO::FETCH_ASSOC);
echo ($row['Create View'] ?? '') . "\n\n";

echo "=== payments indexes ===\n";
foreach ($pdo->query('SHOW INDEX FROM payments') as $i) {
    echo $i['Key_name'] . ' -> ' . $i['Column_name'] . ($i['Non_unique'] ? '' : ' UNIQUE') . "\n";
}

echo "\n=== Sample counts ===\n";
foreach (['reservations','rental_agreements','rentals','invoices','payments','payment_refunds','deposit_transactions','vehicle_inspections'] as $t) {
    echo "$t: " . $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . "\n";
}

echo "\n=== Confirmed reservations without agreement ===\n";
echo $pdo->query("SELECT COUNT(*) FROM reservations r LEFT JOIN rental_agreements a ON a.reservation_id=r.reservation_id WHERE r.status='confirmed' AND a.agreement_id IS NULL")->fetchColumn() . "\n";

echo "\n=== Roles with RO permissions ===\n";
$stmt = $pdo->query("SELECT r.role_name, GROUP_CONCAT(p.permission_key ORDER BY p.permission_key) AS perms FROM roles r JOIN role_permissions rp ON rp.role_id=r.role_id JOIN permissions p ON p.permission_id=rp.permission_id WHERE p.permission_key IN ('rental.view','agreements.manage','rentals.manage','inspections.manage','invoices.manage','payments.manage','refunds.manage','refunds.approve','refunds.process','deposits.manage') GROUP BY r.role_id");
foreach ($stmt as $row) {
    echo $row['role_name'] . ': ' . $row['perms'] . "\n";
}

echo "\n=== invoice_items item_type ===\n";
$col = $pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'item_type'")->fetch();
echo ($col['Type'] ?? '?') . "\n";

echo "\n=== vehicle_inspections enums ===\n";
foreach (['inspection_type','exterior_condition','interior_condition','cleanliness'] as $c) {
    $col = $pdo->query("SHOW COLUMNS FROM vehicle_inspections LIKE '$c'")->fetch();
    echo "$c: " . ($col['Type'] ?? '?') . "\n";
}

echo "\n=== vehicles.status ===\n";
$col = $pdo->query("SHOW COLUMNS FROM vehicles LIKE 'status'")->fetch();
echo ($col['Type'] ?? '?') . "\n";

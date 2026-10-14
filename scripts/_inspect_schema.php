<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$files = glob(dirname(__DIR__) . '/{app,admin,includes}/*.php', GLOB_BRACE) ?: [];
foreach ($files as $file) {
    $out = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        echo "SYNTAX FAIL: $file\n" . implode("\n", $out) . "\n";
    }
}
echo "Syntax sweep done (" . count($files) . " files).\n\n";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=carrental;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "DB OK\n";
} catch (Throwable $e) {
    echo 'DB FAIL: ' . $e->getMessage() . "\n";
    exit(1);
}

$tables = [
    'rental_agreements', 'rentals', 'vehicle_inspections', 'inspection_images',
    'invoices', 'invoice_items', 'payments', 'payment_refunds', 'deposit_transactions',
    'reservations', 'maintenance_records', 'customers', 'vehicles', 'users', 'roles',
    'permissions', 'role_permissions', 'audit_logs', 'system_settings',
];
foreach ($tables as $t) {
    try {
        $pdo->query('SELECT 1 FROM `' . $t . '` LIMIT 1');
        echo "$t: OK\n";
    } catch (Throwable $e) {
        echo "$t: MISSING\n";
    }
}

echo "\n--- Views ---\n";
try {
    $pdo->query('SELECT 1 FROM vw_invoice_balances LIMIT 1');
    echo "vw_invoice_balances: OK\n";
} catch (Throwable $e) {
    echo "vw_invoice_balances: MISSING\n";
}

echo "\n--- Column samples ---\n";
foreach (['rentals', 'rental_agreements', 'invoices', 'payments', 'payment_refunds', 'deposit_transactions', 'vehicle_inspections', 'reservations'] as $t) {
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM `' . $t . '`')->fetchAll();
        echo "$t: " . implode(', ', array_column($cols, 'Field')) . "\n";
    } catch (Throwable $e) {
        echo "$t: cannot describe\n";
    }
}

echo "\n--- Enums / status columns ---\n";
foreach ([
    'rentals.status', 'rental_agreements.status', 'invoices.status', 'payments.status',
    'payment_refunds.status', 'deposit_transactions.transaction_type', 'reservations.status',
] as $spec) {
    [$t, $c] = explode('.', $spec);
    try {
        $row = $pdo->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->fetch();
        echo "$spec: " . ($row['Type'] ?? '?') . "\n";
    } catch (Throwable $e) {
        echo "$spec: n/a\n";
    }
}

echo "\n--- Permissions present ---\n";
try {
    $keys = $pdo->query("SELECT permission_key FROM permissions WHERE permission_key LIKE 'rental%' OR permission_key LIKE 'agreement%' OR permission_key LIKE 'invoice%' OR permission_key LIKE 'payment%' OR permission_key LIKE 'refund%' OR permission_key LIKE 'deposit%' OR permission_key LIKE 'inspection%'")->fetchAll(PDO::FETCH_COLUMN);
    echo implode(', ', $keys) ?: '(none)';
    echo "\n";
} catch (Throwable $e) {
    echo "permissions table unavailable\n";
}

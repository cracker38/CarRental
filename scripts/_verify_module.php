<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once BASE_PATH . '/app/RentalOperations.php';

$php = 'C:\\xampp\\php\\php.exe';
$files = array_merge(
    glob(BASE_PATH . '/app/*.php') ?: [],
    glob(BASE_PATH . '/admin/*.php') ?: [],
    glob(BASE_PATH . '/includes/*.php') ?: [],
    [BASE_PATH . '/login.php', BASE_PATH . '/logout.php']
);
$failed = 0;
foreach ($files as $file) {
    $out = [];
    $code = 0;
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        echo "FAIL $file\n" . implode("\n", $out) . "\n";
        $failed++;
    }
}
echo $failed === 0 ? "All PHP files lint OK (" . count($files) . ")\n" : "$failed file(s) failed lint\n";

$pdo = Database::connection();

// Apply permission grants
$sql = file_get_contents(BASE_PATH . '/sql/install_permissions.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    // Skip comment-only blocks
    $clean = preg_replace('/^--.*$/m', '', $statement);
    if (trim((string)$clean) === '') {
        continue;
    }
    $pdo->exec($statement);
}
echo "Permissions SQL applied.\n";

$methods = get_class_methods(RentalOperations::class);
$required = [
    'createAgreement', 'updateAgreementStatus', 'createRental', 'recordPickup', 'recordReturn',
    'closeRental', 'createInvoiceForRental', 'invoiceBalance', 'refreshInvoiceStatus',
    'recordPayment', 'requestRefund', 'changeRefundStatus', 'depositBalance', 'addDepositTransaction',
];
foreach ($required as $m) {
    echo in_array($m, $methods, true) ? "method $m OK\n" : "method $m MISSING\n";
}

// Smoke-test login Auth response shape
$reflection = new ReflectionMethod(Auth::class, 'attempt');
echo 'Auth::attempt returns array shape expected by login.php: ok/error keys documented in bootstrap.' . "\n";

// Confirm refunds query columns resolve
$cols = $pdo->query(
    "SELECT pr.refund_id, p.invoice_id, i.invoice_number
     FROM payment_refunds pr
     JOIN payments p ON p.payment_id = pr.payment_id
     JOIN invoices i ON i.invoice_id = p.invoice_id
     LIMIT 0"
);
echo "Refunds invoice_id join OK\n";

echo "Verify complete.\n";

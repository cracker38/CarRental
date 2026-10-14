<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end smoke test for Rental Operations workflows.
 * Creates temporary rows inside a transaction and rolls everything back.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require_once BASE_PATH . '/app/RentalOperations.php';

$pdo = Database::connection();
$testFailed = false;
$pdo->beginTransaction();

try {
    $customerId = (int)$pdo->query('SELECT customer_id FROM customers LIMIT 1')->fetchColumn();
    $vehicleId = (int)$pdo->query("SELECT vehicle_id FROM vehicles WHERE status IN ('available','reserved') LIMIT 1")->fetchColumn();
    $userId = (int)$pdo->query("SELECT user_id FROM users WHERE account_status = 'active' LIMIT 1")->fetchColumn();

    if (!$customerId || !$vehicleId || !$userId) {
        throw new RuntimeException('Need at least one customer, available vehicle, and active user for the smoke test.');
    }

    $pdo->prepare("UPDATE vehicles SET current_mileage = GREATEST(COALESCE(current_mileage,0), 10000), fuel_level_percent = 80, status = 'available' WHERE vehicle_id = ?")
        ->execute([$vehicleId]);

    $code = 'SMOKE-' . strtoupper(bin2hex(random_bytes(3)));
    $pdo->prepare(
        "INSERT INTO reservations
         (reservation_code, customer_id, vehicle_id, pickup_datetime, return_datetime, pickup_location, return_location,
          daily_rate, estimated_days, estimated_amount, discount_amount, tax_amount, security_deposit, status, created_by, confirmed_by, confirmed_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY), 'HQ', 'HQ', 50000, 2, 100000, 0, 0, 200000, 'confirmed', ?, ?, NOW())"
    )->execute([$code, $customerId, $vehicleId, $userId, $userId]);
    $reservationId = (int)$pdo->lastInsertId();
    echo "Reservation $code #$reservationId\n";

    $agreementId = RentalOperations::createAgreement($pdo, $reservationId, $userId);
    echo "Agreement #$agreementId\n";
    RentalOperations::updateAgreementStatus($pdo, $agreementId, 'issued', $userId);
    RentalOperations::updateAgreementStatus($pdo, $agreementId, 'signed', $userId);
    echo "Agreement signed\n";

    $rentalId = RentalOperations::createRental($pdo, $agreementId, $userId);
    echo "Rental #$rentalId\n";

    $mileage = (int)$pdo->query("SELECT current_mileage FROM vehicles WHERE vehicle_id = $vehicleId")->fetchColumn();
    RentalOperations::recordPickup($pdo, $rentalId, $mileage, 80.0, [
        'exterior_condition' => 'good',
        'interior_condition' => 'good',
        'cleanliness' => 'clean',
        'damage_notes' => '',
        'other_notes' => 'Smoke pickup',
    ], $userId);
    echo "Pickup recorded\n";

    RentalOperations::addDepositTransaction($pdo, [
        'transaction_type' => 'received',
        'amount' => 200000,
        'rental_id' => $rentalId,
        'payment_method' => 'cash',
        'reason' => 'Smoke deposit receipt',
    ], $userId);
    echo "Deposit received; held=" . RentalOperations::depositBalance($pdo, $reservationId, $rentalId) . "\n";

    RentalOperations::recordReturn($pdo, $rentalId, $mileage + 120, 70.0, [
        'extra_mileage_fee' => 5000,
        'late_return_fee' => 50000,
        'damage_fee' => 15000,
        'cleaning_fee' => 0,
        'other_charges' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ], [
        'exterior_condition' => 'fair',
        'interior_condition' => 'good',
        'cleanliness' => 'clean',
        'damage_notes' => 'Minor scrape on rear bumper',
        'other_notes' => 'Smoke return',
    ], $userId);
    $status = $pdo->query("SELECT status FROM rentals WHERE rental_id = $rentalId")->fetchColumn();
    echo "Return recorded; rental status=$status\n";

    $resStatus = $pdo->query("SELECT status FROM reservations WHERE reservation_id = $reservationId")->fetchColumn();
    echo "Reservation status=$resStatus\n";

    $invoiceId = RentalOperations::createInvoiceForRental($pdo, $rentalId, $userId);
    echo "Invoice #$invoiceId\n";

    $pdo->prepare("UPDATE invoices SET status = 'issued', issue_date = CURDATE(), due_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY) WHERE invoice_id = ?")->execute([$invoiceId]);
    $bal = RentalOperations::invoiceBalance($pdo, $invoiceId);
    echo "Outstanding before payment: {$bal['balance']}\n";

    $paymentId = RentalOperations::recordPayment($pdo, $invoiceId, $bal['balance'] / 2, 'cash', 'SMOKE-REF-' . bin2hex(random_bytes(2)), null, 'Partial smoke payment', $userId);
    echo "Partial payment #$paymentId\n";
    $bal = RentalOperations::invoiceBalance($pdo, $invoiceId);
    RentalOperations::refreshInvoiceStatus($pdo, $invoiceId);
    $invStatus = $pdo->query("SELECT status FROM invoices WHERE invoice_id = $invoiceId")->fetchColumn();
    echo "After partial: status=$invStatus balance={$bal['balance']}\n";

    $paymentId2 = RentalOperations::recordPayment($pdo, $invoiceId, $bal['balance'], 'mobile_money', 'SMOKE-REF-' . bin2hex(random_bytes(2)), 'MTN', 'Final smoke payment', $userId);
    echo "Final payment #$paymentId2\n";
    RentalOperations::refreshInvoiceStatus($pdo, $invoiceId);
    $invStatus = $pdo->query("SELECT status FROM invoices WHERE invoice_id = $invoiceId")->fetchColumn();
    echo "Invoice status=$invStatus\n";

    RentalOperations::addDepositTransaction($pdo, [
        'transaction_type' => 'deduction',
        'amount' => 15000,
        'rental_id' => $rentalId,
        'reason' => 'Agreed damage deduction matching damage fee',
    ], $userId);
    RentalOperations::addDepositTransaction($pdo, [
        'transaction_type' => 'returned',
        'amount' => RentalOperations::depositBalance($pdo, $reservationId, $rentalId),
        'rental_id' => $rentalId,
        'payment_method' => 'cash',
        'reason' => 'Return remaining deposit',
    ], $userId);
    echo "Deposit settled; held=" . RentalOperations::depositBalance($pdo, $reservationId, $rentalId) . "\n";

    RentalOperations::closeRental($pdo, $rentalId, $userId);
    $finalStatus = $pdo->query("SELECT status FROM rentals WHERE rental_id = $rentalId")->fetchColumn();
    echo "Rental closed; status=$finalStatus\n";

    // Refund path on second payment
    $refundId = RentalOperations::requestRefund($pdo, $paymentId2, 1000, 'Smoke test refund', 'cash', null, $userId);
    // Use a different user for approval if available
    $approver = (int)$pdo->query("SELECT user_id FROM users WHERE account_status='active' AND user_id <> $userId LIMIT 1")->fetchColumn();
    if (!$approver) {
        // Schema may allow null approved_by; force different id by temporarily clearing self-approve check via second user create — skip if alone
        echo "Only one user; skipping refund approval self-check by using null approver path...\n";
        // Directly approve as null user won't match requested_by if we set requested_by and approver different — requested is $userId
        // changeRefundStatus blocks same user. Create temp user.
        $pdo->exec("INSERT INTO users (role_id, first_name, last_name, email, password_hash, account_status) SELECT role_id, 'Smoke', 'Approver', CONCAT('smoke',UNIX_TIMESTAMP(),'@test.local'), 'x', 'active' FROM users WHERE user_id = $userId LIMIT 1");
        $approver = (int)$pdo->lastInsertId();
    }
    RentalOperations::changeRefundStatus($pdo, $refundId, 'approved', $approver);
    RentalOperations::changeRefundStatus($pdo, $refundId, 'processed', $approver);
    RentalOperations::refreshInvoiceStatus($pdo, $invoiceId);
    $invStatus = $pdo->query("SELECT status FROM invoices WHERE invoice_id = $invoiceId")->fetchColumn();
    $bal = RentalOperations::invoiceBalance($pdo, $invoiceId);
    echo "After refund: invoice=$invStatus outstanding={$bal['balance']}\n";

    echo "\nSMOKE TEST PASSED\n";
} catch (Throwable $e) {
    $testFailed = true;
    echo "\nSMOKE TEST FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

if ($pdo->inTransaction()) {
    $pdo->rollBack();
    echo "Transaction rolled back (no lasting data changes).\n";
}

if ($testFailed) {
    exit(1);
}

<?php
declare(strict_types=1);

/**
 * Rental Operations service for the carrental schema supplied with this project.
 * Uses explicit transactions for financial and status-changing workflows.
 */
final class RentalOperations
{
    private static int $savepointSequence = 0;

    private static function beginScope(PDO $pdo): ?string
    {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            return null;
        }
        $savepoint = 'ro_scope_' . ++self::$savepointSequence;
        $pdo->exec('SAVEPOINT ' . $savepoint);
        return $savepoint;
    }

    private static function commitScope(PDO $pdo, ?string $savepoint): void
    {
        if ($savepoint === null) {
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return;
        }
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
    }

    private static function rollbackScope(PDO $pdo, ?string $savepoint): void
    {
        if ($savepoint === null) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return;
        }
        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
    }

    public static function makeNumber(string $prefix, int $id): string
    {
        return $prefix . '-' . date('Ymd') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public static function refreshOverdueRentals(PDO $pdo): void
    {
        $pdo->exec("UPDATE rentals SET status = 'overdue' WHERE status = 'active' AND actual_return_datetime IS NULL AND expected_return_datetime < NOW()");
    }

    private static function audit(PDO $pdo, ?int $userId, string $action, string $entityType, ?string $entityId, string $description, ?array $oldValues = null, ?array $newValues = null): void
    {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent, result) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'success')");
        $stmt->execute([
            $userId, $action, $entityType, $entityId, substr($description, 0, 1000),
            $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR),
            $newValues === null ? null : json_encode($newValues, JSON_THROW_ON_ERROR),
            isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);
    }

    public static function logEvent(PDO $pdo, ?int $userId, string $action, string $entityType, string $entityId, string $description, ?array $oldValues = null, ?array $newValues = null): void
    {
        self::audit($pdo, $userId, $action, $entityType, $entityId, $description, $oldValues, $newValues);
    }

    public static function createAgreement(PDO $pdo, int $reservationId, ?int $userId): int
    {
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare(
                'SELECT r.*, c.first_name, c.last_name, v.make, v.model, v.registration_no
                 FROM reservations r
                 JOIN customers c ON c.customer_id = r.customer_id
                 JOIN vehicles v ON v.vehicle_id = r.vehicle_id
                 WHERE r.reservation_id = ? FOR UPDATE'
            );
            $stmt->execute([$reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reservation) {
                throw new RuntimeException('Reservation was not found.');
            }
            if ($reservation['status'] !== 'confirmed') {
                throw new RuntimeException('An agreement can only be generated for a confirmed reservation.');
            }
            $existing = $pdo->prepare('SELECT agreement_id FROM rental_agreements WHERE reservation_id = ?');
            $existing->execute([$reservationId]);
            $existingId = $existing->fetchColumn();
            if ($existingId) {
                self::commitScope($pdo, $savepoint);
                return (int) $existingId;
            }
            $terms = "RENTAL AGREEMENT\n\n" .
                "The customer agrees to return the vehicle at the agreed return time and location, maintain the vehicle in reasonable condition, and pay the agreed rental amount and any separately agreed, documented additional charges. The customer is responsible for reporting damage or incidents promptly. The security deposit may be applied only to documented and agreed deductions, with any remaining balance returned according to the rental company's policy. The customer confirms that the pickup and return inspection records form part of this agreement.\n\n" .
                "Vehicle: {$reservation['make']} {$reservation['model']} ({$reservation['registration_no']})\n" .
                "Reservation: {$reservation['reservation_code']}\n" .
                "Pickup: {$reservation['pickup_datetime']}\nReturn: {$reservation['return_datetime']}\n" .
                "Agreed daily rate: {$reservation['daily_rate']}\nSecurity deposit: {$reservation['security_deposit']}";
            $number = self::makeNumber('AGR', $reservationId);
            $insert = $pdo->prepare(
                'INSERT INTO rental_agreements
                 (agreement_number, reservation_id, customer_id, vehicle_id, prepared_by, agreed_daily_rate, security_deposit, terms_and_conditions, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'draft\')'
            );
            $insert->execute([
                $number, $reservationId, $reservation['customer_id'], $reservation['vehicle_id'], $userId,
                $reservation['daily_rate'], $reservation['security_deposit'], $terms
            ]);
            $id = (int) $pdo->lastInsertId();
            self::audit($pdo, $userId, 'agreement.created', 'rental_agreement', (string)$id, 'Rental agreement ' . $number . ' generated from confirmed reservation ' . $reservation['reservation_code'], null, ['status' => 'draft', 'reservation_id' => $reservationId]);
            self::commitScope($pdo, $savepoint);
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function updateAgreementStatus(PDO $pdo, int $agreementId, string $status, ?int $userId = null): void
    {
        $allowed = ['draft' => ['issued'], 'issued' => ['signed']];
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare('SELECT status FROM rental_agreements WHERE agreement_id = ? FOR UPDATE');
            $stmt->execute([$agreementId]);
            $current = $stmt->fetchColumn();
            if (!$current) throw new RuntimeException('Agreement was not found.');
            if (!in_array($status, $allowed[(string) $current] ?? [], true)) {
                throw new RuntimeException('That agreement status change is not allowed. Follow the draft → issued → signed workflow.');
            }
            $sql = $status === 'signed'
                ? 'UPDATE rental_agreements SET status = ?, signed_at = NOW() WHERE agreement_id = ?'
                : 'UPDATE rental_agreements SET status = ? WHERE agreement_id = ?';
            $pdo->prepare($sql)->execute([$status, $agreementId]);
            self::audit($pdo, $userId, 'agreement.status_changed', 'rental_agreement', (string)$agreementId, 'Agreement status changed from ' . $current . ' to ' . $status, ['status' => $current], ['status' => $status]);
            self::commitScope($pdo, $savepoint);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function createRental(PDO $pdo, int $agreementId, ?int $userId): int
    {
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare(
                'SELECT a.*, r.reservation_code, r.status AS reservation_status, r.requested_driver_id,
                        r.return_datetime, r.estimated_amount, r.estimated_days, r.daily_rate, r.discount_amount, r.tax_amount
                 FROM rental_agreements a JOIN reservations r ON r.reservation_id = a.reservation_id
                 WHERE a.agreement_id = ? FOR UPDATE'
            );
            $stmt->execute([$agreementId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Agreement was not found.');
            if ($row['status'] !== 'signed') throw new RuntimeException('The agreement must be signed before a rental can be opened.');
            if ($row['reservation_status'] !== 'confirmed') throw new RuntimeException('The reservation must still be confirmed.');
            $exists = $pdo->prepare('SELECT rental_id FROM rentals WHERE reservation_id = ? OR agreement_id = ?');
            $exists->execute([$row['reservation_id'], $agreementId]);
            $existingId = $exists->fetchColumn();
            if ($existingId) { self::commitScope($pdo, $savepoint); return (int) $existingId; }
            $number = self::makeNumber('RNT', $agreementId);
            $insert = $pdo->prepare(
                'INSERT INTO rentals
                 (rental_number, reservation_id, agreement_id, customer_id, vehicle_id, assigned_driver_id,
                  expected_return_datetime, base_amount, discount_amount, tax_amount, final_amount, status, handled_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'scheduled\', ?)'
            );
            $base = (float) $row['estimated_amount'];
            if ($base <= 0) $base = (float)$row['daily_rate'] * (float)$row['estimated_days'];
            $discount = (float) $row['discount_amount'];
            $tax = (float) $row['tax_amount'];
            $final = max(0, $base - $discount + $tax);
            $insert->execute([
                $number, $row['reservation_id'], $agreementId, $row['customer_id'], $row['vehicle_id'],
                $row['requested_driver_id'], $row['return_datetime'], $base, $discount, $tax, $final, $userId
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE rental_agreements SET status = 'active' WHERE agreement_id = ?")->execute([$agreementId]);
            self::audit($pdo, $userId, 'rental.created', 'rental', (string)$id, 'Rental ' . $number . ' opened from signed agreement ' . $agreementId, null, ['status' => 'scheduled', 'reservation_id' => (int)$row['reservation_id'], 'base_amount' => $base]);
            self::commitScope($pdo, $savepoint);
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function recordPickup(PDO $pdo, int $rentalId, int $mileage, float $fuel, array $inspection, ?int $userId): void
    {
        if ($mileage < 0 || $fuel < 0 || $fuel > 100) throw new RuntimeException('Enter valid pickup mileage and fuel percentage.');
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare('SELECT r.*, v.current_mileage, v.status AS vehicle_status FROM rentals r JOIN vehicles v ON v.vehicle_id = r.vehicle_id WHERE r.rental_id = ? FOR UPDATE');
            $stmt->execute([$rentalId]);
            $rental = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rental) throw new RuntimeException('Rental was not found.');
            if ($rental['status'] !== 'scheduled' || $rental['actual_pickup_datetime'] !== null) throw new RuntimeException('Only a scheduled rental can be picked up.');
            if ($mileage < (int) $rental['current_mileage']) throw new RuntimeException('Pickup mileage cannot be lower than the vehicle’s current recorded mileage.');
            if (in_array($rental['vehicle_status'], ['maintenance', 'inactive', 'rented'], true)) throw new RuntimeException('This vehicle is not available for pickup.');
            $conflict = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE vehicle_id = ? AND rental_id <> ? AND status IN ('active','overdue')");
            $conflict->execute([$rental['vehicle_id'], $rentalId]);
            if ((int)$conflict->fetchColumn() > 0) throw new RuntimeException('Another active rental already uses this vehicle.');
            $pdo->prepare("UPDATE rentals SET actual_pickup_datetime = NOW(), pickup_mileage = ?, pickup_fuel_percent = ?, status = 'active', handled_by = ? WHERE rental_id = ?")
                ->execute([$mileage, $fuel, $userId, $rentalId]);
            $pdo->prepare("UPDATE vehicles SET current_mileage = ?, fuel_level_percent = ?, status = 'rented' WHERE vehicle_id = ?")
                ->execute([$mileage, $fuel, $rental['vehicle_id']]);
            $pdo->prepare("INSERT INTO vehicle_inspections (rental_id, vehicle_id, inspected_by, inspection_type, odometer_reading, fuel_level_percent, exterior_condition, interior_condition, cleanliness, damage_notes, other_notes) VALUES (?, ?, ?, 'pickup', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$rentalId, $rental['vehicle_id'], $userId, $mileage, $fuel, $inspection['exterior_condition'] ?? 'good', $inspection['interior_condition'] ?? 'good', $inspection['cleanliness'] ?? 'clean', $inspection['damage_notes'] ?? null, $inspection['other_notes'] ?? null]);
            self::audit($pdo, $userId, 'rental.pickup_recorded', 'rental', (string)$rentalId, 'Vehicle pickup recorded', null, ['mileage' => $mileage, 'fuel_percent' => $fuel]);
            self::commitScope($pdo, $savepoint);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function recordReturn(PDO $pdo, int $rentalId, int $mileage, float $fuel, array $charges, array $inspection, ?int $userId): void
    {
        if ($mileage < 0 || $fuel < 0 || $fuel > 100) throw new RuntimeException('Enter valid return mileage and fuel percentage.');
        foreach (['extra_mileage_fee','late_return_fee','damage_fee','cleaning_fee','other_charges','discount_amount','tax_amount'] as $key) {
            if (!isset($charges[$key]) || !is_numeric($charges[$key]) || (float) $charges[$key] < 0) throw new RuntimeException('All charge amounts must be valid non-negative numbers.');
        }
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare('SELECT r.*, v.current_mileage FROM rentals r JOIN vehicles v ON v.vehicle_id = r.vehicle_id WHERE r.rental_id = ? FOR UPDATE');
            $stmt->execute([$rentalId]);
            $rental = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rental) throw new RuntimeException('Rental was not found.');
            if (!in_array($rental['status'], ['active','overdue'], true) || !$rental['actual_pickup_datetime']) throw new RuntimeException('Only an active or overdue rental can be returned.');
            if ($mileage < (int) $rental['pickup_mileage'] || $mileage < (int) $rental['current_mileage']) throw new RuntimeException('Return mileage must not be below pickup or current recorded mileage.');
            $base = (float) $rental['base_amount'];
            $subtotal = $base + (float)$charges['extra_mileage_fee'] + (float)$charges['late_return_fee'] + (float)$charges['damage_fee'] + (float)$charges['cleaning_fee'] + (float)$charges['other_charges'];
            $discount = (float)$charges['discount_amount'];
            $tax = (float)$charges['tax_amount'];
            if ($discount > $subtotal) throw new RuntimeException('Discount cannot exceed the rental subtotal.');
            $final = max(0, $subtotal - $discount + $tax);
            $status = (($inspection['exterior_condition'] ?? '') === 'poor'
                || ($inspection['interior_condition'] ?? '') === 'poor'
                || ($inspection['cleanliness'] ?? '') === 'needs_cleaning'
                || trim((string)($inspection['damage_notes'] ?? '')) !== ''
                || (float)$charges['damage_fee'] > 0)
                ? 'inspected'
                : 'returned';
            $pdo->prepare("UPDATE rentals SET actual_return_datetime = NOW(), return_mileage = ?, return_fuel_percent = ?, extra_mileage_fee = ?, late_return_fee = ?, damage_fee = ?, cleaning_fee = ?, other_charges = ?, discount_amount = ?, tax_amount = ?, final_amount = ?, status = ?, return_notes = ?, handled_by = ? WHERE rental_id = ?")
                ->execute([$mileage, $fuel, $charges['extra_mileage_fee'], $charges['late_return_fee'], $charges['damage_fee'], $charges['cleaning_fee'], $charges['other_charges'], $discount, $tax, $final, $status, $inspection['other_notes'] ?? null, $userId, $rentalId]);
            $pdo->prepare('UPDATE vehicles SET current_mileage = ?, fuel_level_percent = ?, status = ? WHERE vehicle_id = ?')
                ->execute([$mileage, $fuel, $status === 'inspected' ? 'maintenance' : 'available', $rental['vehicle_id']]);
            $pdo->prepare("UPDATE rental_agreements SET status = 'completed' WHERE agreement_id = ? AND status = 'active'")->execute([$rental['agreement_id']]);
            $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = ? AND status = 'confirmed'")->execute([$rental['reservation_id']]);
            $pdo->prepare("INSERT INTO vehicle_inspections (rental_id, vehicle_id, inspected_by, inspection_type, odometer_reading, fuel_level_percent, exterior_condition, interior_condition, cleanliness, damage_notes, other_notes) VALUES (?, ?, ?, 'return', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$rentalId, $rental['vehicle_id'], $userId, $mileage, $fuel, $inspection['exterior_condition'] ?? 'good', $inspection['interior_condition'] ?? 'good', $inspection['cleanliness'] ?? 'clean', $inspection['damage_notes'] ?? null, $inspection['other_notes'] ?? null]);
            if ($status === 'inspected') {
                $maintenanceNumber = self::makeNumber('MNT', $rentalId);
                $check = $pdo->prepare('SELECT maintenance_id FROM maintenance_records WHERE maintenance_number = ?');
                $check->execute([$maintenanceNumber]);
                if (!$check->fetchColumn()) {
                    $reason = trim((string)($inspection['damage_notes'] ?? '')) ?: 'Vehicle flagged for post-return inspection/service.';
                    $pdo->prepare("INSERT INTO maintenance_records (maintenance_number, vehicle_id, reported_by, maintenance_type, title, description, start_date, odometer_reading, cost, status, notes) VALUES (?, ?, ?, 'inspection', 'Post-rental inspection required', ?, CURDATE(), ?, ?, 'scheduled', ?)")
                        ->execute([$maintenanceNumber, $rental['vehicle_id'], $userId, $reason, $mileage, $charges['damage_fee'], $inspection['other_notes'] ?? null]);
                }
            }
            self::audit($pdo, $userId, 'rental.return_recorded', 'rental', (string)$rentalId, 'Vehicle return recorded and rental charges recalculated', null, ['mileage' => $mileage, 'fuel_percent' => $fuel, 'extra_mileage_fee' => (float)$charges['extra_mileage_fee'], 'late_return_fee' => (float)$charges['late_return_fee'], 'damage_fee' => (float)$charges['damage_fee'], 'cleaning_fee' => (float)$charges['cleaning_fee'], 'other_charges' => (float)$charges['other_charges'], 'discount_amount' => $discount, 'tax_amount' => $tax, 'final_amount' => $final, 'status' => $status]);
            self::commitScope($pdo, $savepoint);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function closeRental(PDO $pdo, int $rentalId, ?int $userId): void
    {
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare('SELECT rental_id, rental_number, reservation_id, status FROM rentals WHERE rental_id = ? FOR UPDATE');
            $stmt->execute([$rentalId]);
            $rental = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rental) {
                throw new RuntimeException('Rental was not found.');
            }
            if (!in_array($rental['status'], ['returned', 'inspected'], true)) {
                throw new RuntimeException('Only returned or inspected rentals can be closed.');
            }
            $stmt = $pdo->prepare("SELECT invoice_id, status FROM invoices WHERE rental_id = ? AND status <> 'void' ORDER BY invoice_id DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([$rentalId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) {
                throw new RuntimeException('Generate and issue an invoice before closing this rental.');
            }
            $balance = self::invoiceBalance($pdo, (int)$invoice['invoice_id']);
            if ($balance['balance'] > 0.009) {
                throw new RuntimeException('Settle or void the outstanding invoice before closing this rental.');
            }
            if (self::depositBalance($pdo, (int)$rental['reservation_id'], $rentalId, true) > 0.009) {
                throw new RuntimeException('Return or deduct the remaining security deposit before closing this rental.');
            }
            $pdo->prepare("UPDATE rentals SET status = 'closed', handled_by = ? WHERE rental_id = ?")->execute([$userId, $rentalId]);
            self::audit($pdo, $userId, 'rental.closed', 'rental', (string)$rentalId, 'Rental ' . $rental['rental_number'] . ' closed', ['status' => $rental['status']], ['status' => 'closed']);
            self::commitScope($pdo, $savepoint);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                self::rollbackScope($pdo, $savepoint);
            }
            throw $e;
        }
    }

    public static function createInvoiceForRental(PDO $pdo, int $rentalId, ?int $userId): int
    {
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare('SELECT r.*, rs.reservation_code FROM rentals r JOIN reservations rs ON rs.reservation_id = r.reservation_id WHERE r.rental_id = ? FOR UPDATE');
            $stmt->execute([$rentalId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$r) throw new RuntimeException('Rental was not found.');
            if (!in_array($r['status'], ['returned', 'inspected', 'closed', 'active', 'overdue'], true)) {
                throw new RuntimeException('An invoice can only be created after pickup has occurred.');
            }
            if (in_array($r['status'], ['active', 'overdue'], true)) {
                throw new RuntimeException('Complete the vehicle return and calculate final charges before generating the invoice.');
            }
            $existing = $pdo->prepare("SELECT invoice_id FROM invoices WHERE rental_id = ? AND status <> 'void' ORDER BY invoice_id DESC LIMIT 1");
            $existing->execute([$rentalId]);
            $existingId = $existing->fetchColumn();
            if ($existingId) { self::commitScope($pdo, $savepoint); return (int)$existingId; }
            $items = [];
            $base = (float)$r['base_amount'];
            if ($base > 0) $items[] = ['rental', 'Vehicle rental', 1, $base];
            foreach ([
                'extra_mileage_fee' => ['extra_mileage','Extra mileage'],
                'late_return_fee' => ['late_return','Late return'],
                'damage_fee' => ['damage','Damage charge'],
                'cleaning_fee' => ['cleaning','Cleaning charge'],
                'other_charges' => ['other','Other agreed charges'],
            ] as $field => [$type,$label]) {
                $amount = (float)$r[$field];
                if ($amount > 0) $items[] = [$type, $label, 1, $amount];
            }
            $subtotal = array_sum(array_map(static fn($i) => (float)$i[3], $items));
            $discount = (float)$r['discount_amount'];
            $tax = (float)$r['tax_amount'];
            $total = max(0, $subtotal - $discount + $tax);
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string)$rentalId, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(2)));
            $currency = function_exists('setting') ? (string)setting('default_currency', 'RWF') : 'RWF';
            if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'RWF';
            $pdo->prepare("INSERT INTO invoices (invoice_number, customer_id, reservation_id, rental_id, issued_by, issue_date, due_date, subtotal, discount_amount, tax_amount, total_amount, currency, status, notes) VALUES (?, ?, ?, ?, ?, CURDATE(), NULL, ?, ?, ?, ?, ?, 'draft', ?)")
                ->execute([$invoiceNumber, $r['customer_id'], $r['reservation_id'], $rentalId, $userId, $subtotal, $discount, $tax, $total, $currency, 'Generated from rental ' . $r['rental_number'] . ' / reservation ' . $r['reservation_code']]);
            $invoiceId = (int)$pdo->lastInsertId();
            $insertItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as [$type,$description,$quantity,$unitPrice]) $insertItem->execute([$invoiceId,$type,$description,$quantity,$unitPrice,$quantity*$unitPrice]);
            if ($discount > 0) $insertItem->execute([$invoiceId,'discount','Agreed discount',1,$discount,0]);
            if ($tax > 0) $insertItem->execute([$invoiceId,'tax','Tax',1,$tax,$tax]);
            self::audit($pdo, $userId, 'invoice.created', 'invoice', (string)$invoiceId, 'Draft invoice ' . $invoiceNumber . ' generated from rental ' . $r['rental_number'], null, ['total_amount' => $total, 'currency' => $currency, 'status' => 'draft']);
            self::commitScope($pdo, $savepoint);
            return $invoiceId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function invoiceBalance(PDO $pdo, int $invoiceId, bool $lock = false): array
    {
        $invoiceSql = 'SELECT invoice_id, total_amount, status, due_date FROM invoices WHERE invoice_id = ?' . ($lock ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($invoiceSql); $stmt->execute([$invoiceId]); $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) throw new RuntimeException('Invoice was not found.');
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND status IN ('completed','partially_refunded','refunded')");
        $stmt->execute([$invoiceId]); $grossPaid = (float)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(pr.amount),0) FROM payment_refunds pr JOIN payments p ON p.payment_id = pr.payment_id WHERE p.invoice_id = ? AND pr.status = 'processed'");
        $stmt->execute([$invoiceId]); $refunded = (float)$stmt->fetchColumn();
        $netPaid = max(0, $grossPaid - $refunded);
        return ['invoice'=>$invoice, 'gross_paid'=>$grossPaid, 'refunded'=>$refunded, 'net_paid'=>$netPaid, 'balance'=>max(0, (float)$invoice['total_amount'] - $netPaid)];
    }

    public static function refreshInvoiceStatus(PDO $pdo, int $invoiceId): void
    {
        $data = self::invoiceBalance($pdo, $invoiceId);
        $invoice = $data['invoice'];
        if (in_array($invoice['status'], ['void', 'draft'], true)) {
            return;
        }
        $due = !empty($invoice['due_date']) && $invoice['due_date'] < date('Y-m-d');
        if ($data['gross_paid'] > 0.009 && $data['net_paid'] <= 0.009) {
            $status = 'refunded';
        } elseif ($data['balance'] <= 0.009) {
            $status = 'paid';
        } elseif ($data['net_paid'] > 0.009) {
            $status = $due ? 'overdue' : 'partially_paid';
        } else {
            $status = $due ? 'overdue' : 'issued';
        }
        $pdo->prepare('UPDATE invoices SET status = ? WHERE invoice_id = ?')->execute([$status, $invoiceId]);
    }

    public static function recordPayment(PDO $pdo, int $invoiceId, float $amount, string $method, ?string $reference, ?string $provider, ?string $notes, ?int $userId): int
    {
        if (!is_finite($amount) || $amount <= 0) throw new RuntimeException('Payment amount must be greater than zero.');
        $methods = ['cash','bank_transfer','card','mobile_money','online','other'];
        if (!in_array($method, $methods, true)) throw new RuntimeException('Select a valid payment method.');
        $savepoint = self::beginScope($pdo);
        try {
            $data = self::invoiceBalance($pdo, $invoiceId, true);
            if (in_array($data['invoice']['status'], ['draft', 'void', 'refunded'], true)) {
                throw new RuntimeException('Issue the invoice before recording a payment.');
            }
            if ($data['balance'] <= 0.009) {
                throw new RuntimeException('This invoice has no outstanding balance.');
            }
            if ($amount > $data['balance'] + 0.009) {
                throw new RuntimeException('Payment exceeds the outstanding balance. Record only the amount actually received.');
            }
            $reference = $reference !== null ? trim($reference) : null;
            if ($reference === '') {
                $reference = null;
            }
            if ($reference !== null) {
                $dup = $pdo->prepare('SELECT payment_id FROM payments WHERE transaction_reference = ? LIMIT 1');
                $dup->execute([$reference]);
                if ($dup->fetchColumn()) {
                    throw new RuntimeException('That transaction reference is already recorded on another payment. Use a unique provider/bank reference.');
                }
            }
            $number = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            try {
                $stmt = $pdo->prepare("INSERT INTO payments (payment_number, invoice_id, received_by, amount, payment_method, transaction_reference, status, provider_name, notes) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?)");
                $stmt->execute([$number, $invoiceId, $userId, $amount, $method, $reference, $provider ?: null, $notes ?: null]);
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'uq_payment_transaction_reference') || str_contains($e->getMessage(), 'Duplicate')) {
                    throw new RuntimeException('That transaction reference is already recorded on another payment. Use a unique provider/bank reference.');
                }
                throw $e;
            }
            $paymentId = (int)$pdo->lastInsertId();
            self::refreshInvoiceStatus($pdo, $invoiceId);
            self::audit($pdo, $userId, 'payment.recorded', 'payment', (string)$paymentId, 'Payment ' . $number . ' recorded against invoice ' . $invoiceId, null, ['amount' => $amount, 'method' => $method, 'status' => 'completed']);
            self::commitScope($pdo, $savepoint);
            return $paymentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                self::rollbackScope($pdo, $savepoint);
            }
            throw $e;
        }
    }

    public static function requestRefund(PDO $pdo, int $paymentId, float $amount, string $reason, string $method, ?string $reference, ?int $userId): int
    {
        if (!is_finite($amount) || $amount <= 0) throw new RuntimeException('Refund amount must be greater than zero.');
        if (trim($reason) === '') throw new RuntimeException('A refund reason is required.');
        if (!in_array($method, ['cash','bank_transfer','card','mobile_money','original_method','other'], true)) throw new RuntimeException('Select a valid refund method.');
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare('SELECT p.*, i.status AS invoice_status FROM payments p JOIN invoices i ON i.invoice_id = p.invoice_id WHERE p.payment_id = ? FOR UPDATE');
            $stmt->execute([$paymentId]); $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$payment) throw new RuntimeException('Payment was not found.');
            if (!in_array($payment['status'], ['completed','partially_refunded'], true)) throw new RuntimeException('Only completed, not-fully-refunded payments can be refunded.');
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_refunds WHERE payment_id = ? AND status IN ('requested','approved','processed')");
            $stmt->execute([$paymentId]); $reserved = (float)$stmt->fetchColumn();
            if ($amount > (float)$payment['amount'] - $reserved + 0.009) throw new RuntimeException('Refund exceeds the unrefunded amount for this payment.');
            $number = 'REF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare("INSERT INTO payment_refunds (payment_id, refund_number, amount, reason, refund_method, transaction_reference, status, requested_by) VALUES (?, ?, ?, ?, ?, ?, 'requested', ?)");
            $stmt->execute([$paymentId,$number,$amount,trim($reason),$method,$reference ?: null,$userId]);
            $id = (int)$pdo->lastInsertId();
            self::audit($pdo, $userId, 'refund.requested', 'payment_refund', (string)$id, 'Refund ' . $number . ' requested for payment ' . $paymentId, null, ['amount' => $amount, 'status' => 'requested']);
            self::commitScope($pdo, $savepoint);
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function changeRefundStatus(PDO $pdo, int $refundId, string $newStatus, ?int $userId): void
    {
        if (!in_array($newStatus, ['approved','processed','rejected','failed'], true)) throw new RuntimeException('Invalid refund action.');
        $savepoint = self::beginScope($pdo);
        try {
            $stmt = $pdo->prepare('SELECT pr.*, p.invoice_id FROM payment_refunds pr JOIN payments p ON p.payment_id = pr.payment_id WHERE pr.refund_id = ? FOR UPDATE');
            $stmt->execute([$refundId]); $refund = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$refund) throw new RuntimeException('Refund request was not found.');
            if ($newStatus === 'approved') {
                if ($refund['status'] !== 'requested') throw new RuntimeException('Only requested refunds can be approved.');
                if ($refund['requested_by'] !== null && $userId !== null && (int)$refund['requested_by'] === $userId) throw new RuntimeException('A staff member cannot approve their own refund request.');
                $pdo->prepare("UPDATE payment_refunds SET status = 'approved', approved_by = ? WHERE refund_id = ?")->execute([$userId,$refundId]);
            } elseif ($newStatus === 'processed') {
                if ($refund['status'] !== 'approved') {
                    throw new RuntimeException('A refund must be approved before it can be processed.');
                }
                $pdo->prepare("UPDATE payment_refunds SET status = 'processed', processed_at = NOW() WHERE refund_id = ?")->execute([$refundId]);
                $stmt = $pdo->prepare("SELECT p.amount, COALESCE((SELECT SUM(pr.amount) FROM payment_refunds pr WHERE pr.payment_id = p.payment_id AND pr.status = 'processed'),0) AS refunded FROM payments p WHERE p.payment_id = ?");
                $stmt->execute([$refund['payment_id']]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                $pStatus = ((float)$p['refunded'] >= (float)$p['amount'] - 0.009) ? 'refunded' : 'partially_refunded';
                $pdo->prepare('UPDATE payments SET status = ? WHERE payment_id = ?')->execute([$pStatus, $refund['payment_id']]);
                self::refreshInvoiceStatus($pdo, (int)$refund['invoice_id']);
            } else {
                if (!in_array($refund['status'], ['requested','approved'], true)) throw new RuntimeException('Only requested or approved refunds can be rejected or marked failed.');
                $pdo->prepare('UPDATE payment_refunds SET status = ? WHERE refund_id = ?')->execute([$newStatus,$refundId]);
            }
            self::audit($pdo, $userId, 'refund.' . $newStatus, 'payment_refund', (string)$refundId, 'Refund ' . $refund['refund_number'] . ' changed to ' . $newStatus, ['status' => $refund['status']], ['status' => $newStatus, 'amount' => (float)$refund['amount']]);
            self::commitScope($pdo, $savepoint);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }

    public static function depositBalance(PDO $pdo, ?int $reservationId, ?int $rentalId, bool $lock = false): float
    {
        if ($rentalId !== null && $reservationId !== null) {
            // Carry forward any deposit received against the reservation before a rental record existed.
            $sql = "SELECT transaction_type, amount FROM deposit_transactions WHERE (rental_id = ? OR (rental_id IS NULL AND reservation_id = ?)) AND status = 'completed'";
            $params = [$rentalId, $reservationId];
        } elseif ($rentalId !== null) {
            $sql = "SELECT transaction_type, amount FROM deposit_transactions WHERE rental_id = ? AND status = 'completed'";
            $params = [$rentalId];
        } elseif ($reservationId !== null) {
            $sql = "SELECT transaction_type, amount FROM deposit_transactions WHERE reservation_id = ? AND status = 'completed'";
            $params = [$reservationId];
        } else throw new RuntimeException('A reservation or rental is required for a deposit transaction.');
        $stmt = $pdo->prepare($sql . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute($params); $balance = 0.0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $amount = (float)$row['amount'];
            if ($row['transaction_type'] === 'received' || $row['transaction_type'] === 'adjustment') $balance += $amount;
            elseif (in_array($row['transaction_type'], ['deduction','refund','returned'], true)) $balance -= $amount;
        }
        return round($balance, 2);
    }

    public static function addDepositTransaction(PDO $pdo, array $data, ?int $userId): int
    {
        $amount = (float)($data['amount'] ?? 0);
        if (!is_finite($amount) || $amount <= 0) throw new RuntimeException('Deposit transaction amount must be greater than zero.');
        $type = (string)($data['transaction_type'] ?? '');
        if (!in_array($type, ['received','deduction','refund','returned','adjustment'], true)) throw new RuntimeException('Invalid deposit transaction type.');
        if (trim((string)($data['reason'] ?? '')) === '') throw new RuntimeException('A reason or supporting note is required for every deposit transaction.');
        $reservationId = !empty($data['reservation_id']) ? (int)$data['reservation_id'] : null;
        $rentalId = !empty($data['rental_id']) ? (int)$data['rental_id'] : null;
        if (!$reservationId && !$rentalId) throw new RuntimeException('Choose a reservation or rental.');
        $savepoint = self::beginScope($pdo);
        try {
            if ($rentalId) {
                $stmt = $pdo->prepare('SELECT r.reservation_id, r.customer_id, a.security_deposit FROM rentals r JOIN rental_agreements a ON a.agreement_id = r.agreement_id WHERE r.rental_id = ? FOR UPDATE'); $stmt->execute([$rentalId]); $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare('SELECT reservation_id, customer_id, security_deposit FROM reservations WHERE reservation_id = ? FOR UPDATE'); $stmt->execute([$reservationId]); $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$owner) throw new RuntimeException('Reservation or rental was not found.');
            if ($rentalId) $reservationId = (int)$owner['reservation_id'];
            $balance = self::depositBalance($pdo, $reservationId, $rentalId, true);
            if (in_array($type, ['deduction','refund','returned'], true) && $amount > $balance + 0.009) throw new RuntimeException('This transaction exceeds the deposit currently held.');
            if (in_array($type, ['received','adjustment'], true)) {
                $depositTarget = (float)($owner['security_deposit'] ?? 0);
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM deposit_transactions WHERE reservation_id = ? AND transaction_type = 'received' AND status = 'completed'");
                $stmt->execute([$reservationId]);
                $alreadyReceived = (float)$stmt->fetchColumn();
                if ($depositTarget <= 0) throw new RuntimeException('No security deposit is configured on this reservation/agreement. Confirm the agreed deposit before recording a deposit receipt.');
                $projectedBalance = $type === 'received' ? $alreadyReceived + $amount : $balance + $amount;
                if ($projectedBalance > $depositTarget + 0.009) throw new RuntimeException('This transaction would exceed the agreed security deposit.');
            }
            $method = trim((string)($data['payment_method'] ?? ''));
            $allowedMethods = ['cash','bank_transfer','card','mobile_money','online','other'];
            if ($method !== '' && !in_array($method, $allowedMethods, true)) throw new RuntimeException('Invalid payment method.');
            $stmt = $pdo->prepare("INSERT INTO deposit_transactions (reservation_id, rental_id, customer_id, transaction_type, amount, payment_method, reference_number, reason, processed_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
            $stmt->execute([$reservationId,$rentalId,$owner['customer_id'],$type,$amount,$method ?: null,trim((string)($data['reference_number'] ?? '')) ?: null,trim((string)($data['reason'] ?? '')) ?: null,$userId]);
            $id = (int)$pdo->lastInsertId();
            self::audit($pdo, $userId, 'deposit.transaction_recorded', 'deposit_transaction', (string)$id, 'Security deposit transaction recorded', null, ['type' => $type, 'amount' => $amount, 'reservation_id' => $reservationId, 'rental_id' => $rentalId]);
            self::commitScope($pdo, $savepoint); return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackScope($pdo, $savepoint);
            throw $e;
        }
    }
}

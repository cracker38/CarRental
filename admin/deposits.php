<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireLogin();
require_once BASE_PATH . '/includes/rental-operations-common.php';
ro_require_permission('deposits.manage');

$pdo = Database::connection();
$prefillRentalId = (int)($_GET['rental_id'] ?? 0);
$prefillReservationId = (int)($_GET['reservation_id'] ?? 0);

if (is_post()) {
    ro_post_guard();
    try {
        $payload = $_POST;
        // Prefer a single owner record to avoid conflicting reservation/rental pairs.
        if (!empty($payload['rental_id'])) {
            $payload['reservation_id'] = '';
        }
        RentalOperations::addDepositTransaction($pdo, $payload, isset($user['user_id']) ? (int)$user['user_id'] : null);
        ro_flash('success', 'Deposit transaction recorded. Deposit balance has been validated.');
        redirect('admin/deposits.php');
    } catch (Throwable $e) {
        ro_flash('danger', $e->getMessage());
        redirect('admin/deposits.php');
    }
}

$reservations = $pdo->query(
    "SELECT r.reservation_id, r.reservation_code, r.security_deposit, c.first_name, c.last_name
     FROM reservations r
     JOIN customers c ON c.customer_id = r.customer_id
     WHERE r.status IN ('confirmed','completed')
     ORDER BY r.reservation_id DESC
     LIMIT 250"
)->fetchAll(PDO::FETCH_ASSOC);

$rentals = $pdo->query(
    "SELECT r.rental_id, r.rental_number, r.reservation_id, a.security_deposit, c.first_name, c.last_name, v.registration_no
     FROM rentals r
     JOIN rental_agreements a ON a.agreement_id = r.agreement_id
     JOIN customers c ON c.customer_id = r.customer_id
     JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     WHERE r.status NOT IN ('cancelled')
     ORDER BY r.rental_id DESC
     LIMIT 250"
)->fetchAll(PDO::FETCH_ASSOC);

$rows = $pdo->query(
    'SELECT d.*, c.first_name, c.last_name, r.reservation_code, rt.rental_number,
            u.first_name AS staff_first, u.last_name AS staff_last
     FROM deposit_transactions d
     JOIN customers c ON c.customer_id = d.customer_id
     LEFT JOIN reservations r ON r.reservation_id = d.reservation_id
     LEFT JOIN rentals rt ON rt.rental_id = d.rental_id
     LEFT JOIN users u ON u.user_id = d.processed_by
     ORDER BY d.deposit_transaction_id DESC
     LIMIT 250'
)->fetchAll(PDO::FETCH_ASSOC);

$openBalances = [];
$stmtBalances = $pdo->query(
    "SELECT r.rental_id, r.rental_number, r.reservation_id, r.status, a.security_deposit,
            c.first_name, c.last_name, v.registration_no
     FROM rentals r
     JOIN rental_agreements a ON a.agreement_id = r.agreement_id
     JOIN customers c ON c.customer_id = r.customer_id
     JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     WHERE r.status IN ('active','overdue','returned','inspected','closed','scheduled')
     ORDER BY r.rental_id DESC
     LIMIT 80"
);
foreach ($stmtBalances->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $held = RentalOperations::depositBalance($pdo, (int)$row['reservation_id'], (int)$row['rental_id']);
    if ($held > 0.009) {
        $row['held'] = $held;
        $openBalances[] = $row;
    }
}

$pageTitle = 'Security Deposits';
$currentPage = 'deposits';
require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/rental-operations-nav.php';
?>
<div class="ro-shell">
    <section class="ro-page-heading">
        <div>
            <span class="ro-kicker">SECURITY DEPOSIT LEDGER</span>
            <h1>Deposits</h1>
            <p>Track deposit receipts, agreed deductions, refunds and final returns with a complete transaction trail.</p>
        </div>
        <div class="ro-heading-symbol"><i class="bi bi-safe2"></i></div>
    </section>

    <?php if ($openBalances): ?>
        <section class="ro-panel mb-4">
            <div class="ro-panel-head">
                <div><span class="ro-kicker">HELD FUNDS</span><h2>Deposits currently held</h2></div>
                <span class="ro-counter"><?= count($openBalances) ?> open</span>
            </div>
            <div class="table-responsive">
                <table class="table ro-table align-middle">
                    <thead>
                        <tr>
                            <th>Rental</th>
                            <th>Customer</th>
                            <th>Vehicle</th>
                            <th>Status</th>
                            <th>Agreed</th>
                            <th>Held now</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($openBalances as $b): ?>
                            <tr>
                                <td><strong><?= e($b['rental_number']) ?></strong></td>
                                <td><?= e($b['first_name'] . ' ' . $b['last_name']) ?></td>
                                <td><?= e($b['registration_no']) ?></td>
                                <td><?= ro_status_badge($b['status']) ?></td>
                                <td><?= ro_money($b['security_deposit']) ?></td>
                                <td><strong><?= ro_money($b['held']) ?></strong></td>
                                <td><a class="btn btn-sm ro-btn-outline" href="<?= e(base_url('admin/deposits.php?rental_id=' . (int)$b['rental_id'] . '#deposit-form')) ?>">Post entry</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="ro-panel mb-4" id="deposit-form">
        <div class="ro-panel-head">
            <div>
                <span class="ro-kicker">NEW LEDGER ENTRY</span>
                <h2>Record a deposit transaction</h2>
                <p>Receipts are limited to the agreed deposit; deductions and returns cannot exceed the amount held.</p>
            </div>
        </div>
        <form method="post" class="ro-form-grid">
            <?= csrf_field() ?>
            <div class="ro-field">
                <label for="transaction_type">Transaction type *</label>
                <select class="form-select" id="transaction_type" name="transaction_type" required>
                    <option value="received">Deposit received</option>
                    <option value="deduction">Deduction for agreed cost</option>
                    <option value="refund">Refund deposit amount</option>
                    <option value="returned">Return remaining deposit</option>
                    <option value="adjustment">Positive balance adjustment</option>
                </select>
            </div>
            <div class="ro-field">
                <label for="amount">Amount (<?= e((string)setting('default_currency', 'RWF')) ?>) *</label>
                <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="ro-field ro-field-full">
                <label for="rental_id">Rental (recommended after rental opens)</label>
                <select class="form-select" id="rental_id" name="rental_id">
                    <option value="">— Choose rental or use reservation below —</option>
                    <?php foreach ($rentals as $r): ?>
                        <option value="<?= (int)$r['rental_id'] ?>" <?= $prefillRentalId === (int)$r['rental_id'] ? 'selected' : '' ?>>
                            <?= e($r['rental_number'] . ' · ' . $r['first_name'] . ' ' . $r['last_name'] . ' · ' . $r['registration_no'] . ' · agreed deposit ' . number_format((float)$r['security_deposit'], 2)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ro-field ro-field-full">
                <label for="reservation_id">Reservation (for a deposit before rental opens)</label>
                <select class="form-select" id="reservation_id" name="reservation_id">
                    <option value="">— Choose reservation —</option>
                    <?php foreach ($reservations as $r): ?>
                        <option value="<?= (int)$r['reservation_id'] ?>" <?= $prefillReservationId === (int)$r['reservation_id'] && !$prefillRentalId ? 'selected' : '' ?>>
                            <?= e($r['reservation_code'] . ' · ' . $r['first_name'] . ' ' . $r['last_name'] . ' · agreed deposit ' . number_format((float)$r['security_deposit'], 2)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-secondary">Select either a rental or a reservation, not both. Existing reservation deposits are carried forward when a rental is selected.</small>
            </div>
            <div class="ro-field">
                <label for="payment_method">Payment method</label>
                <select class="form-select" id="payment_method" name="payment_method">
                    <option value="">Not applicable</option>
                    <?php foreach (['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'card' => 'Card', 'mobile_money' => 'Mobile money', 'online' => 'Online', 'other' => 'Other'] as $v => $label): ?>
                        <option value="<?= e($v) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ro-field">
                <label for="reference_number">Reference number</label>
                <input class="form-control" id="reference_number" name="reference_number" maxlength="150">
            </div>
            <div class="ro-field ro-field-full">
                <label for="reason">Reason / supporting notes *</label>
                <textarea class="form-control" id="reason" name="reason" rows="2" maxlength="500" required placeholder="e.g. deposit receipt reference, agreed damage deduction, or deposit returned to customer"></textarea>
            </div>
            <div class="ro-field ro-field-full">
                <div class="ro-note"><i class="bi bi-info-circle"></i> “Adjustment” is treated as a positive credit. Use “deduction” for any decrease. Deposit ledger entries are accounting records; they do not transfer money by themselves.</div>
            </div>
            <div class="ro-form-actions">
                <button class="btn ro-btn-primary" type="submit"><i class="bi bi-plus-circle me-1"></i> Save deposit transaction</button>
            </div>
        </form>
    </section>

    <section class="ro-panel">
        <div class="ro-panel-head">
            <div><span class="ro-kicker">DEPOSIT REGISTER</span><h2>Latest transactions</h2></div>
            <span class="ro-counter"><?= count($rows) ?> records</span>
        </div>
        <?php if (!$rows): ?>
            <div class="ro-empty">
                <div class="ro-empty-icon"><i class="bi bi-safe2"></i></div>
                <strong>No deposit transactions yet</strong>
                <p>Record deposit receipts and later deductions or returns here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table ro-table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Reservation / rental</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Reason</th>
                            <th>Processed by</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $d): ?>
                            <tr>
                                <td><?= e(ro_date($d['transaction_date'])) ?></td>
                                <td><?= e($d['first_name'] . ' ' . $d['last_name']) ?></td>
                                <td><?= e($d['rental_number'] ?: $d['reservation_code'] ?: '—') ?></td>
                                <td><?= ro_status_badge($d['transaction_type']) ?></td>
                                <td><strong><?= ro_money($d['amount']) ?></strong></td>
                                <td><?= e($d['payment_method'] ? ucwords(str_replace('_', ' ', $d['payment_method'])) : '—') ?></td>
                                <td><?= e($d['reference_number'] ?: '—') ?></td>
                                <td class="ro-reason-cell"><?= e($d['reason'] ?: '—') ?></td>
                                <td><?= e(trim(($d['staff_first'] ?? '') . ' ' . ($d['staff_last'] ?? '')) ?: 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    const rental = document.getElementById('rental_id');
    const reservation = document.getElementById('reservation_id');
    rental.addEventListener('change', () => { if (rental.value) reservation.value = ''; });
    reservation.addEventListener('change', () => { if (reservation.value) rental.value = ''; });
})();
</script>
<?php require BASE_PATH . '/includes/footer.php'; ?>

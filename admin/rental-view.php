<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireLogin();
require_once BASE_PATH . '/includes/rental-operations-common.php';
ro_require_permission('rentals.manage');

$pdo = Database::connection();
RentalOperations::refreshOverdueRentals($pdo);
$id = (int)($_GET['id'] ?? $_POST['rental_id'] ?? 0);

if (is_post()) {
    ro_post_guard();
    try {
        $uid = isset($user['user_id']) ? (int)$user['user_id'] : null;
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'pickup') {
            RentalOperations::recordPickup($pdo, $id, (int)($_POST['pickup_mileage'] ?? -1), (float)($_POST['pickup_fuel_percent'] ?? -1), $_POST, $uid);
            ro_flash('success', 'Pickup recorded and the vehicle is marked as rented.');
        } elseif ($action === 'return') {
            RentalOperations::recordReturn($pdo, $id, (int)($_POST['return_mileage'] ?? -1), (float)($_POST['return_fuel_percent'] ?? -1), $_POST, $_POST, $uid);
            ro_flash('success', 'Return recorded, charges calculated, and inspection saved.');
        } elseif ($action === 'invoice') {
            if (!hasPermission('invoices.manage')) {
                throw new RuntimeException('You do not have permission to create invoices.');
            }
            $invoiceId = RentalOperations::createInvoiceForRental($pdo, $id, $uid);
            ro_flash('success', 'Invoice generated with itemized rental charges.');
            redirect('admin/invoice-view.php?id=' . $invoiceId);
        } elseif ($action === 'close') {
            RentalOperations::closeRental($pdo, $id, $uid);
            ro_flash('success', 'Rental closed. The operational record is complete.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        ro_flash('danger', $e->getMessage());
    }
    redirect('admin/rental-view.php?id=' . $id);
}

$stmt = $pdo->prepare(
    'SELECT r.*, a.agreement_number, a.status AS agreement_status, a.security_deposit,
            rs.reservation_code, rs.pickup_datetime, rs.return_datetime, rs.daily_rate,
            c.first_name, c.last_name, c.phone, c.email,
            v.make, v.model, v.registration_no, v.current_mileage,
            v.fuel_level_percent AS vehicle_fuel_level, v.status AS vehicle_status
     FROM rentals r
     JOIN rental_agreements a ON a.agreement_id = r.agreement_id
     JOIN reservations rs ON rs.reservation_id = r.reservation_id
     JOIN customers c ON c.customer_id = r.customer_id
     JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     WHERE r.rental_id = ?'
);
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) {
    http_response_code(404);
    exit('Rental not found.');
}

$insp = $pdo->prepare(
    'SELECT vi.*, u.first_name, u.last_name
     FROM vehicle_inspections vi
     LEFT JOIN users u ON u.user_id = vi.inspected_by
     WHERE vi.rental_id = ?
     ORDER BY vi.inspected_at DESC'
);
$insp->execute([$id]);
$inspections = $insp->fetchAll(PDO::FETCH_ASSOC);

$inv = $pdo->prepare("SELECT invoice_id, invoice_number, status, total_amount FROM invoices WHERE rental_id = ? AND status <> 'void' ORDER BY invoice_id DESC LIMIT 1");
$inv->execute([$id]);
$invoice = $inv->fetch(PDO::FETCH_ASSOC);
$invoiceBalance = $invoice ? RentalOperations::invoiceBalance($pdo, (int)$invoice['invoice_id']) : null;

$depositHeld = RentalOperations::depositBalance($pdo, (int)$r['reservation_id'], $id);
$canCloseRental = $invoiceBalance !== null && $invoiceBalance['balance'] <= 0.009 && $depositHeld <= 0.009;

$lateSeconds = max(0, (strtotime((string)$r['actual_return_datetime']) ?: time()) - (strtotime((string)$r['expected_return_datetime']) ?: time()));
$lateDays = $r['actual_return_datetime']
    ? max(0, (int)ceil($lateSeconds / 86400))
    : max(0, (int)ceil((time() - (strtotime((string)$r['expected_return_datetime']) ?: time())) / 86400));
$suggestedLateFee = ro_suggest_late_fee((float)$r['daily_rate'], $lateDays);
$distance = ($r['return_mileage'] !== null && $r['pickup_mileage'] !== null)
    ? max(0, (int)$r['return_mileage'] - (int)$r['pickup_mileage'])
    : null;

$pageTitle = 'Rental ' . $r['rental_number'];
$currentPage = 'rentals';
require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/rental-operations-nav.php';
?>
<div class="ro-shell">
    <div class="ro-page-heading">
        <div>
            <a class="ro-back-link" href="<?= e(base_url('admin/rentals.php')) ?>"><i class="bi bi-arrow-left"></i> Rental desk</a>
            <h1><?= e($r['rental_number']) ?></h1>
            <p><?= e($r['make'] . ' ' . $r['model'] . ' · ' . $r['registration_no']) ?> · <?= e($r['first_name'] . ' ' . $r['last_name']) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?= ro_status_badge($r['status']) ?>
            <?php if ($invoice): ?>
                <a class="btn ro-btn-outline" href="<?= e(base_url('admin/invoice-view.php?id=' . (int)$invoice['invoice_id'])) ?>">
                    <i class="bi bi-receipt me-1"></i> <?= e($invoice['invoice_number']) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="ro-detail-grid">
        <section class="ro-panel">
            <div class="ro-panel-head">
                <div><span class="ro-kicker">RENTAL SUMMARY</span><h2>Booking details</h2></div>
                <span class="ro-panel-icon"><i class="bi bi-calendar2-check"></i></span>
            </div>
            <div class="ro-detail-list">
                <div><span>Reservation</span><strong><?= e($r['reservation_code']) ?></strong></div>
                <div><span>Agreement</span><strong><?= e($r['agreement_number']) ?></strong></div>
                <div><span>Customer</span><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong><small><?= e($r['phone']) ?> · <?= e($r['email'] ?? '') ?></small></div>
                <div><span>Expected return</span><strong><?= e(ro_date($r['expected_return_datetime'])) ?></strong></div>
                <div><span>Actual pickup</span><strong><?= e(ro_date($r['actual_pickup_datetime'], 'Not recorded')) ?></strong></div>
                <div><span>Actual return</span><strong><?= e(ro_date($r['actual_return_datetime'], 'Not returned')) ?></strong></div>
                <div><span>Base amount</span><strong><?= ro_money($r['base_amount']) ?></strong></div>
                <div><span>Current final amount</span><strong class="ro-amount-large"><?= ro_money($r['final_amount']) ?></strong></div>
                <div><span>Security deposit held</span><strong><?= ro_money($depositHeld) ?></strong><small>Agreed deposit <?= ro_money($r['security_deposit']) ?></small></div>
                <div><span>Vehicle status</span><strong><?= e(ucwords((string)$r['vehicle_status'])) ?></strong></div>
            </div>
        </section>

        <section class="ro-panel">
            <div class="ro-panel-head">
                <div><span class="ro-kicker">MILEAGE &amp; FUEL</span><h2>Vehicle readings</h2></div>
                <span class="ro-panel-icon"><i class="bi bi-speedometer2"></i></span>
            </div>
            <div class="ro-reading-grid">
                <div class="ro-reading"><small>Pickup mileage</small><strong><?= $r['pickup_mileage'] === null ? '—' : number_format((int)$r['pickup_mileage']) . ' km' ?></strong></div>
                <div class="ro-reading"><small>Return mileage</small><strong><?= $r['return_mileage'] === null ? '—' : number_format((int)$r['return_mileage']) . ' km' ?></strong></div>
                <div class="ro-reading"><small>Distance driven</small><strong><?= $distance === null ? 'Pending' : number_format($distance) . ' km' ?></strong></div>
                <div class="ro-reading">
                    <small>Late return</small>
                    <strong><?= ($r['actual_return_datetime'] || in_array($r['status'], ['active', 'overdue'], true)) ? $lateDays . ' day(s)' : '—' ?></strong>
                    <small class="text-secondary">Suggested late fee uses daily rate × overdue days.</small>
                </div>
            </div>
            <div class="ro-note mt-3"><i class="bi bi-info-circle"></i> Fuel is stored as a percentage from 0 to 100. Mileage cannot be lower than the vehicle's current reading.</div>
        </section>
    </div>

    <?php if ($r['status'] === 'scheduled'): ?>
        <section class="ro-panel mt-4">
            <div class="ro-panel-head">
                <div>
                    <span class="ro-kicker">STEP 1</span>
                    <h2>Record vehicle pickup</h2>
                    <p>Complete these readings and the condition check before handing over the keys.</p>
                </div>
                <span class="ro-step-number">01</span>
            </div>
            <form method="post" class="ro-form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pickup">
                <input type="hidden" name="rental_id" value="<?= $id ?>">
                <div class="ro-field">
                    <label for="pickup_mileage">Odometer (km) *</label>
                    <input class="form-control" id="pickup_mileage" name="pickup_mileage" type="number" min="<?= (int)$r['current_mileage'] ?>" value="<?= (int)$r['current_mileage'] ?>" required>
                </div>
                <div class="ro-field">
                    <label for="pickup_fuel_percent">Fuel level (%) *</label>
                    <input class="form-control" id="pickup_fuel_percent" name="pickup_fuel_percent" type="number" min="0" max="100" step="0.01" value="<?= e((string)($r['vehicle_fuel_level'] ?? 0)) ?>" required>
                </div>
                <div class="ro-field">
                    <label for="exterior_condition">Exterior condition</label>
                    <select class="form-select" id="exterior_condition" name="exterior_condition">
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
                <div class="ro-field">
                    <label for="interior_condition">Interior condition</label>
                    <select class="form-select" id="interior_condition" name="interior_condition">
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
                <div class="ro-field">
                    <label for="cleanliness">Cleanliness</label>
                    <select class="form-select" id="cleanliness" name="cleanliness">
                        <option value="clean" selected>Clean</option>
                        <option value="acceptable">Acceptable</option>
                        <option value="needs_cleaning">Needs cleaning</option>
                    </select>
                </div>
                <div class="ro-field ro-field-full">
                    <label for="damage_notes">Existing damage notes</label>
                    <textarea class="form-control" id="damage_notes" name="damage_notes" rows="2" placeholder="Record visible marks, dents or other pre-existing damage"></textarea>
                </div>
                <div class="ro-field ro-field-full">
                    <label for="other_notes">Pickup notes</label>
                    <textarea class="form-control" id="other_notes" name="other_notes" rows="2" placeholder="Keys, documents, accessories and customer acknowledgements"></textarea>
                </div>
                <div class="ro-form-actions">
                    <button class="btn ro-btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Save pickup and activate rental</button>
                </div>
            </form>
        </section>

    <?php elseif (in_array($r['status'], ['active', 'overdue'], true)): ?>
        <section class="ro-panel mt-4">
            <div class="ro-panel-head">
                <div>
                    <span class="ro-kicker">STEP 2</span>
                    <h2>Record vehicle return</h2>
                    <p>Enter return readings and agreed additional charges. The final amount is calculated automatically on save.</p>
                </div>
                <span class="ro-step-number">02</span>
            </div>
            <div class="ro-note mb-3">
                <i class="bi bi-clock-history"></i>
                The rental is <?= $lateDays > 0 ? 'past its expected return by ' . $lateDays . ' day(s)' : 'within or at its expected return window' ?>.
                <?php if ($suggestedLateFee > 0): ?>
                    A suggested late fee of <?= ro_money($suggestedLateFee) ?> (daily rate × <?= $lateDays ?> day(s)) is pre-filled — adjust to your approved policy before saving.
                <?php else: ?>
                    No late fee is auto-invented when the return is on time.
                <?php endif; ?>
            </div>
            <form method="post" class="ro-form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="return">
                <input type="hidden" name="rental_id" value="<?= $id ?>">
                <div class="ro-field">
                    <label for="return_mileage">Return odometer (km) *</label>
                    <input class="form-control" id="return_mileage" name="return_mileage" type="number" min="<?= (int)$r['pickup_mileage'] ?>" value="<?= (int)$r['current_mileage'] ?>" required>
                </div>
                <div class="ro-field">
                    <label for="return_fuel_percent">Return fuel (%) *</label>
                    <input class="form-control" id="return_fuel_percent" name="return_fuel_percent" type="number" min="0" max="100" step="0.01" value="<?= e((string)($r['pickup_fuel_percent'] ?? 0)) ?>" required>
                </div>
                <?php
                $chargeFields = [
                    ['extra_mileage_fee', 'Extra mileage fee', (float)$r['extra_mileage_fee']],
                    ['late_return_fee', 'Late return fee', $suggestedLateFee > 0 ? $suggestedLateFee : (float)$r['late_return_fee']],
                    ['damage_fee', 'Damage fee', (float)$r['damage_fee']],
                    ['cleaning_fee', 'Cleaning fee', (float)$r['cleaning_fee']],
                    ['other_charges', 'Other agreed charges', (float)$r['other_charges']],
                    ['discount_amount', 'Discount', (float)$r['discount_amount']],
                    ['tax_amount', 'Tax', (float)$r['tax_amount']],
                ];
                foreach ($chargeFields as [$name, $label, $value]):
                ?>
                    <div class="ro-field">
                        <label for="<?= e($name) ?>"><?= e($label) ?> (<?= e((string)setting('default_currency', 'RWF')) ?>)</label>
                        <input class="form-control charge-input" id="<?= e($name) ?>" name="<?= e($name) ?>" type="number" min="0" step="0.01" value="<?= e(number_format($value, 2, '.', '')) ?>" required>
                    </div>
                <?php endforeach; ?>
                <div class="ro-field">
                    <label>Estimated final total</label>
                    <div class="ro-total-preview" id="ro-final-preview"><?= ro_money($r['base_amount']) ?></div>
                    <small class="text-secondary">Base + fees − discount + tax</small>
                </div>
                <div class="ro-field">
                    <label for="exterior_condition">Exterior condition</label>
                    <select class="form-select" id="exterior_condition" name="exterior_condition">
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
                <div class="ro-field">
                    <label for="interior_condition">Interior condition</label>
                    <select class="form-select" id="interior_condition" name="interior_condition">
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
                <div class="ro-field">
                    <label for="cleanliness">Cleanliness</label>
                    <select class="form-select" id="cleanliness" name="cleanliness">
                        <option value="clean" selected>Clean</option>
                        <option value="acceptable">Acceptable</option>
                        <option value="needs_cleaning">Needs cleaning</option>
                    </select>
                </div>
                <div class="ro-field ro-field-full">
                    <label for="damage_notes">Damage found / repair notes</label>
                    <textarea class="form-control" id="damage_notes" name="damage_notes" rows="2" placeholder="Describe new damage; this will flag the vehicle for maintenance follow-up"></textarea>
                </div>
                <div class="ro-field ro-field-full">
                    <label for="other_notes">Return notes</label>
                    <textarea class="form-control" id="other_notes" name="other_notes" rows="2"></textarea>
                </div>
                <div class="ro-form-actions">
                    <button class="btn ro-btn-primary" type="submit"><i class="bi bi-check2-square me-1"></i> Save return and calculate charges</button>
                </div>
            </form>
        </section>
        <script>
        (function () {
            const base = <?= json_encode((float)$r['base_amount']) ?>;
            const names = ['extra_mileage_fee','late_return_fee','damage_fee','cleaning_fee','other_charges','discount_amount','tax_amount'];
            const currency = <?= json_encode((string)setting('default_currency', 'RWF')) ?>;
            const fmt = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            function update() {
                const vals = {};
                names.forEach(n => vals[n] = Math.max(0, Number(document.getElementById(n)?.value || 0)));
                const total = Math.max(0, base + vals.extra_mileage_fee + vals.late_return_fee + vals.damage_fee + vals.cleaning_fee + vals.other_charges - vals.discount_amount + vals.tax_amount);
                document.getElementById('ro-final-preview').textContent = currency + ' ' + fmt.format(total);
            }
            document.querySelectorAll('.charge-input').forEach(el => el.addEventListener('input', update));
            update();
        })();
        </script>

    <?php else: ?>
        <section class="ro-panel mt-4">
            <div class="ro-panel-head">
                <div><span class="ro-kicker">BILLING &amp; CLOSURE</span><h2>Rental charges</h2></div>
                <strong class="ro-amount-large"><?= ro_money($r['final_amount']) ?></strong>
            </div>
            <div class="ro-charge-breakdown">
                <?php
                $lines = [
                    'Base rental' => (float)$r['base_amount'],
                    'Extra mileage' => (float)$r['extra_mileage_fee'],
                    'Late return' => (float)$r['late_return_fee'],
                    'Damage' => (float)$r['damage_fee'],
                    'Cleaning' => (float)$r['cleaning_fee'],
                    'Other charges' => (float)$r['other_charges'],
                    'Discount' => -(float)$r['discount_amount'],
                    'Tax' => (float)$r['tax_amount'],
                ];
                foreach ($lines as $label => $amount):
                    if (abs($amount) < 0.005 && $label !== 'Base rental') continue;
                ?>
                    <div><span><?= e($label) ?></span><strong><?= $amount < 0 ? '− ' . ro_money(abs($amount)) : ro_money($amount) ?></strong></div>
                <?php endforeach; ?>
                <div class="total-line"><span>Final amount</span><strong><?= ro_money($r['final_amount']) ?></strong></div>
            </div>
            <p class="text-secondary mt-3 mb-3">The pickup/return workflow is complete. Generate and settle the invoice, then return or deduct the remaining deposit before closing the rental.</p>
            <div class="ro-workflow-actions">
                <?php if (hasPermission('invoices.manage')): ?>
                    <?php if ($invoice): ?>
                        <a class="btn ro-btn-primary" href="<?= e(base_url('admin/invoice-view.php?id=' . (int)$invoice['invoice_id'])) ?>">
                            <i class="bi bi-receipt me-1"></i> Open <?= e($invoice['invoice_number']) ?>
                        </a>
                    <?php else: ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="invoice">
                            <input type="hidden" name="rental_id" value="<?= $id ?>">
                            <button class="btn ro-btn-primary" type="submit"><i class="bi bi-receipt-cutoff me-1"></i> Generate invoice</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (hasPermission('deposits.manage') && $depositHeld > 0.009): ?>
                    <a class="btn ro-btn-outline" href="<?= e(base_url('admin/deposits.php?rental_id=' . $id)) ?>">
                        <i class="bi bi-safe2 me-1"></i> Manage deposit (<?= ro_money($depositHeld) ?> held)
                    </a>
                <?php endif; ?>
                <?php if ($canCloseRental && in_array($r['status'], ['returned', 'inspected'], true)): ?>
                    <form method="post" onsubmit="return confirm('Close this rental after billing and deposit handling are complete?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="rental_id" value="<?= $id ?>">
                        <button class="btn ro-btn-outline" type="submit"><i class="bi bi-lock me-1"></i> Close rental</button>
                    </form>
                <?php elseif (in_array($r['status'], ['returned', 'inspected'], true)): ?>
                    <span class="ro-note"><i class="bi bi-info-circle"></i> Closing is available after an invoice is fully settled and the held deposit has been returned or deducted.</span>
                <?php endif; ?>
            </div>
            <?php if ($r['status'] === 'inspected'): ?>
                <div class="ro-note mt-3"><i class="bi bi-tools"></i> This vehicle was flagged for post-return inspection or service. A maintenance record was scheduled automatically.</div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="ro-panel mt-4">
        <div class="ro-panel-head">
            <div><span class="ro-kicker">CONDITION HISTORY</span><h2>Vehicle inspections</h2></div>
            <?php if ($r['status'] !== 'scheduled' && hasPermission('inspections.manage')): ?>
                <a class="btn ro-btn-outline" href="<?= e(base_url('admin/inspection-form.php?rental_id=' . $id)) ?>">
                    <i class="bi bi-camera me-1"></i> Add follow-up inspection
                </a>
            <?php endif; ?>
        </div>
        <?php if (!$inspections): ?>
            <div class="ro-empty compact">
                <div class="ro-empty-icon"><i class="bi bi-clipboard-check"></i></div>
                <strong>No inspections recorded</strong>
                <p>Pickup and return inspections are added when those actions are saved.</p>
            </div>
        <?php else: ?>
            <div class="ro-inspection-list">
                <?php foreach ($inspections as $i): ?>
                    <div class="ro-inspection-row">
                        <span class="ro-inspection-icon"><i class="bi bi-clipboard2-check"></i></span>
                        <div class="ro-inspection-copy">
                            <strong><?= e(ucwords(str_replace('_', ' ', $i['inspection_type']))) ?> inspection</strong>
                            <small><?= e(ro_date($i['inspected_at'])) ?> · <?= e(trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')) ?: 'Staff') ?></small>
                            <p><?= e('Odometer: ' . ($i['odometer_reading'] === null ? '—' : number_format((int)$i['odometer_reading']) . ' km') . ' · Fuel: ' . ($i['fuel_level_percent'] === null ? '—' : $i['fuel_level_percent'] . '%') . ' · Exterior: ' . ($i['exterior_condition'] ?: '—') . ' · Interior: ' . ($i['interior_condition'] ?: '—')) ?></p>
                            <?php if ($i['damage_notes']): ?>
                                <div class="ro-note"><i class="bi bi-exclamation-triangle"></i> <?= e($i['damage_notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <a class="ro-text-link" href="<?= e(base_url('admin/inspection-form.php?inspection_id=' . (int)$i['inspection_id'])) ?>">Details <i class="bi bi-arrow-up-right"></i></a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

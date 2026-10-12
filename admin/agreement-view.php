<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireLogin();
require_once BASE_PATH . '/includes/rental-operations-common.php';
ro_require_permission('agreements.manage');
$pdo = Database::connection(); $id = (int)($_GET['id'] ?? $_POST['agreement_id'] ?? 0);
if (is_post()) {
    ro_post_guard();
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'status') RentalOperations::updateAgreementStatus($pdo, $id, (string)($_POST['status'] ?? ''), isset($user['user_id']) ? (int)$user['user_id'] : null);
        elseif ($action === 'create_rental') {
            if (!hasPermission('rentals.manage')) throw new RuntimeException('You do not have permission to open rentals.');
            $rentalId = RentalOperations::createRental($pdo, $id, isset($user['user_id']) ? (int)$user['user_id'] : null);
            ro_flash('success', 'Rental record is ready. Record pickup when the customer collects the vehicle.');
            redirect('admin/rental-view.php?id='.$rentalId);
        } else throw new RuntimeException('Unknown action.');
        ro_flash('success', 'Agreement updated successfully.');
    } catch (Throwable $e) { ro_flash('danger', $e->getMessage()); }
    redirect('admin/agreement-view.php?id='.$id);
}
$stmt = $pdo->prepare('SELECT a.*,r.reservation_code,r.pickup_datetime,r.return_datetime,r.pickup_location,r.return_location,r.estimated_amount,r.status AS reservation_status,c.first_name,c.last_name,c.phone,c.email,c.address_line,c.city,c.country,v.make,v.model,v.registration_no,v.vin,v.colour FROM rental_agreements a JOIN reservations r ON r.reservation_id=a.reservation_id JOIN customers c ON c.customer_id=a.customer_id JOIN vehicles v ON v.vehicle_id=a.vehicle_id WHERE a.agreement_id=?');
$stmt->execute([$id]); $a = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$a) { http_response_code(404); exit('Agreement not found.'); }
$rentalStmt=$pdo->prepare('SELECT rental_id,rental_number,status FROM rentals WHERE agreement_id=?');$rentalStmt->execute([$id]);$rental=$rentalStmt->fetch(PDO::FETCH_ASSOC);
$pageTitle='Agreement '.$a['agreement_number'];$currentPage='agreements';
require BASE_PATH . '/includes/header.php'; require BASE_PATH . '/includes/rental-operations-nav.php';
?>
<div class="ro-shell"><div class="ro-page-heading no-print"><div><a class="ro-back-link" href="<?= e(base_url('admin/agreements.php')) ?>"><i class="bi bi-arrow-left"></i> Agreement register</a><h1><?= e($a['agreement_number']) ?></h1><p>Review the generated contract and progress it through the issue and signature steps.</p></div><div class="d-flex gap-2 align-items-center"><?= ro_status_badge($a['status']) ?><button class="btn ro-btn-outline" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button></div></div>
<div class="ro-document"><div class="ro-document-top"><div class="ro-brand-mark"><i class="bi bi-car-front-fill"></i></div><div><span class="ro-kicker">RENTAL CONTRACT</span><h2><?= e((string)setting('business_name','Car Rental Business')) ?></h2><p>Agreement number <?= e($a['agreement_number']) ?></p></div><div class="ro-document-status"><?= ro_status_badge($a['status']) ?><small>Created <?= e(ro_date($a['created_at'])) ?></small></div></div>
<div class="ro-document-grid"><div class="ro-document-block"><span>RENTER</span><strong><?= e($a['first_name'].' '.$a['last_name']) ?></strong><p><?= e($a['phone']) ?><br><?= e($a['email'] ?? 'No email provided') ?><br><?= e(trim(($a['address_line'] ?? '').' '.($a['city'] ?? '').' '.($a['country'] ?? ''))) ?></p></div><div class="ro-document-block"><span>VEHICLE</span><strong><?= e($a['make'].' '.$a['model']) ?></strong><p>Registration: <?= e($a['registration_no']) ?><br>Colour: <?= e($a['colour'] ?: 'Not recorded') ?><br>VIN: <?= e($a['vin'] ?: 'Not recorded') ?></p></div><div class="ro-document-block"><span>RENTAL PERIOD</span><strong><?= e(ro_date($a['pickup_datetime'])) ?></strong><p>Expected return: <?= e(ro_date($a['return_datetime'])) ?><br>Pickup location: <?= e($a['pickup_location'] ?: 'As agreed') ?><br>Return location: <?= e($a['return_location'] ?: 'As agreed') ?></p></div><div class="ro-document-block"><span>AGREED CHARGES</span><strong><?= ro_money($a['agreed_daily_rate']) ?> <small>/ day</small></strong><p>Security deposit: <?= ro_money($a['security_deposit']) ?><br>Reservation estimate: <?= ro_money($a['estimated_amount']) ?></p></div></div>
<div class="ro-terms"><h3>Terms and conditions</h3><div><?= nl2br(e((string)$a['terms_and_conditions'])) ?></div></div><div class="ro-signature-grid"><div><span>Renter signature</span><div class="ro-signature-line"></div><small>Name and date</small></div><div><span>Authorized staff</span><div class="ro-signature-line"></div><small>Name and date</small></div></div><p class="ro-document-note">This print view is a record generated from the reservation. Confirm the business's approved legal terms and collect the required signatures before treating the agreement as legally executed.</p></div>
<div class="ro-panel mt-4 no-print"><div class="ro-panel-head"><div><span class="ro-kicker">WORKFLOW</span><h2>Next step</h2></div></div><div class="ro-workflow-actions"><?php if ($a['status']==='draft'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="agreement_id" value="<?= $id ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="issued"><button class="btn ro-btn-primary" type="submit"><i class="bi bi-send me-1"></i> Mark as issued</button></form><?php elseif ($a['status']==='issued'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="agreement_id" value="<?= $id ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="signed"><button class="btn ro-btn-primary" type="submit"><i class="bi bi-pen me-1"></i> Record signed agreement</button></form><?php elseif ($a['status']==='signed' && !$rental && hasPermission('rentals.manage')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="agreement_id" value="<?= $id ?>"><input type="hidden" name="action" value="create_rental"><button class="btn ro-btn-primary" type="submit"><i class="bi bi-key me-1"></i> Open rental</button></form><?php elseif ($rental): ?><a class="btn ro-btn-primary" href="<?= e(base_url('admin/rental-view.php?id='.(int)$rental['rental_id'])) ?>">Open <?= e($rental['rental_number']) ?> <i class="bi bi-arrow-right ms-1"></i></a><?php else: ?><span class="text-secondary">No further action is available for this agreement status.</span><?php endif; ?><a class="btn ro-btn-outline" href="<?= e(base_url('admin/agreements.php')) ?>">Back to register</a></div></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

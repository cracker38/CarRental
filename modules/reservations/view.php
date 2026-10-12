<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/availability.php';
date_default_timezone_set('Africa/Kigali');
start_session();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Invalid reservation ID.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $now = date('Y-m-d H:i:s');
    $userId = current_user_id();

    try {
        $pdo->beginTransaction();

        // Lock this reservation row
        $s = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ? FOR UPDATE");
        $s->execute([$id]);
        $res = $s->fetch();
        if (!$res) throw new RuntimeException('Reservation not found.');

        $notify = function (string $type, string $subject, string $message) use ($pdo, $res) {
            $pdo->prepare(
                "INSERT INTO notifications
                 (customer_id, channel, notification_type, subject, message,
                  related_entity_type, related_entity_id, status)
                 VALUES (?, 'in_app', ?, ?, ?, 'reservation', ?, 'queued')"
            )->execute([$res['customer_id'], $type, $subject, $message, $res['reservation_id']]);
        };
        $code = $res['reservation_code'];

        if ($action === 'confirm') {
            if ($res['status'] !== 'pending') {
                throw new RuntimeException('Only pending reservations can be confirmed.');
            }
            if ($res['return_datetime'] <= $now) {
                throw new RuntimeException('The return date has already passed.');
            }

            $s = $pdo->prepare("SELECT status FROM customers WHERE customer_id = ?");
            $s->execute([$res['customer_id']]);
            if ($s->fetchColumn() !== 'active') {
                throw new RuntimeException('The customer is no longer active, so this cannot be confirmed.');
            }

            // Lock the vehicle row, then check overlaps
            $pdo->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? FOR UPDATE")
                ->execute([$res['vehicle_id']]);

            if (!vehicle_is_available($pdo, (int)$res['vehicle_id'],
                    $res['pickup_datetime'], $res['return_datetime'], (int)$res['reservation_id'])) {
                throw new RuntimeException('Cannot confirm: the vehicle is already confirmed for an overlapping period, or is in maintenance.');
            }

            $pdo->prepare(
                "UPDATE reservations SET status = 'confirmed', confirmed_by = ?, confirmed_at = ?
                 WHERE reservation_id = ?"
            )->execute([$userId, $now, $id]);
            $notify('reservation_confirmed', 'Reservation confirmed', "Your reservation $code has been confirmed.");
            $msg = "Reservation $code confirmed.";

        } elseif ($action === 'reject') {
            if ($res['status'] !== 'pending') {
                throw new RuntimeException('Only pending reservations can be rejected.');
            }
            if ($reason === '' || strlen($reason) > 500) {
                throw new RuntimeException('Enter a reason (max 500 characters).');
            }
            $pdo->prepare("UPDATE reservations SET status = 'rejected', cancellation_reason = ? WHERE reservation_id = ?")
                ->execute([$reason, $id]);
            $notify('reservation_rejected', 'Reservation rejected', "Your reservation $code was not accepted.");
            $msg = "Reservation $code rejected.";

        } elseif ($action === 'cancel') {
            if (!in_array($res['status'], ['pending', 'confirmed'], true)) {
                throw new RuntimeException('Only pending or confirmed reservations can be cancelled.');
            }
            if ($reason === '' || strlen($reason) > 500) {
                throw new RuntimeException('Enter a reason (max 500 characters).');
            }
            $s = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE reservation_id = ? AND status <> 'cancelled'");
            $s->execute([$id]);
            if ((int)$s->fetchColumn() > 0) {
                throw new RuntimeException('A rental already exists for this reservation, so it cannot be cancelled here.');
            }
            $pdo->prepare(
                "UPDATE reservations SET status = 'cancelled', cancellation_reason = ?, cancelled_at = ?
                 WHERE reservation_id = ?"
            )->execute([$reason, $now, $id]);
            $notify('reservation_cancelled', 'Reservation cancelled', "Your reservation $code has been cancelled.");
            $msg = "Reservation $code cancelled.";

        } else {
            throw new RuntimeException('Unknown action.');
        }

        $pdo->commit();
        flash($msg);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('Error: ' . $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        flash('Error: the action could not be completed. Please try again.');
    }
    redirect('view.php?id=' . $id);
}

$stmt = $pdo->prepare(
    "SELECT r.*, c.first_name, c.last_name, c.customer_code, c.phone, c.status AS customer_status,
            v.make, v.model, v.registration_no
     FROM reservations r
     JOIN customers c ON c.customer_id = r.customer_id
     JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     WHERE r.reservation_id = ?"
);
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { http_response_code(404); die('Reservation not found.'); }

// Other pending requests competing for the same vehicle and period
$s = $pdo->prepare(
    "SELECT COUNT(*) FROM reservations
     WHERE vehicle_id = ? AND status = 'pending' AND reservation_id <> ?
       AND pickup_datetime < ? AND return_datetime > ?"
);
$s->execute([$r['vehicle_id'], $id, $r['return_datetime'], $r['pickup_datetime']]);
$competing = (int)$s->fetchColumn();

$flash = get_flash();
$isError = $flash && str_starts_with($flash, 'Error:');
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Reservation <?= e($r['reservation_code']) ?></title></head>
<body>
<p><a href="index.php">&larr; Back to reservations</a></p>
<h1>Reservation <?= e($r['reservation_code']) ?></h1>
<?php if ($flash): ?><p style="color:<?= $isError ? 'red' : 'green' ?>"><?= e($flash) ?></p><?php endif; ?>
<?php if ($competing > 0 && $r['status'] === 'pending'): ?>
  <p style="color:#b36b00">Note: <?= $competing ?> other pending request(s) overlap this vehicle and period. Only one can be confirmed.</p>
<?php endif; ?>

<table border="1" cellpadding="6">
  <tr><th>Status</th><td><strong><?= e($r['status']) ?></strong></td></tr>
  <tr><th>Customer</th><td><a href="../customers/view.php?id=<?= (int)$r['customer_id'] ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a> (<?= e($r['customer_code']) ?>) - <?= e($r['phone']) ?></td></tr>
  <tr><th>Vehicle</th><td><?= e($r['make'] . ' ' . $r['model'] . ' (' . $r['registration_no'] . ')') ?></td></tr>
  <tr><th>Pickup</th><td><?= e($r['pickup_datetime']) ?> <?= e($r['pickup_location']) ?></td></tr>
  <tr><th>Return</th><td><?= e($r['return_datetime']) ?> <?= e($r['return_location']) ?></td></tr>
  <tr><th>Rate x days</th><td><?= number_format((float)$r['daily_rate']) ?> x <?= e((string)$r['estimated_days']) ?></td></tr>
  <tr><th>Estimated amount</th><td><?= number_format((float)$r['estimated_amount']) ?> RWF</td></tr>
  <tr><th>Security deposit</th><td><?= number_format((float)$r['security_deposit']) ?> RWF</td></tr>
  <tr><th>Customer notes</th><td><?= e($r['customer_notes']) ?></td></tr>
  <tr><th>Created</th><td><?= e($r['created_at']) ?></td></tr>
  <?php if ($r['confirmed_at']): ?><tr><th>Confirmed</th><td><?= e($r['confirmed_at']) ?></td></tr><?php endif; ?>
  <?php if ($r['cancelled_at']): ?><tr><th>Cancelled</th><td><?= e($r['cancelled_at']) ?></td></tr><?php endif; ?>
  <?php if ($r['cancellation_reason']): ?><tr><th>Reason</th><td><?= e($r['cancellation_reason']) ?></td></tr><?php endif; ?>
</table>

<?php if ($r['status'] === 'pending'): ?>
  <h2>Confirm</h2>
  <form method="post" onsubmit="return confirm('Confirm this reservation?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="confirm">
    <button type="submit">Confirm reservation</button>
  </form>

  <h2>Reject</h2>
  <form method="post" onsubmit="return confirm('Reject this reservation?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reject">
    <p><input name="reason" placeholder="Reason" maxlength="500" size="50" required></p>
    <button type="submit">Reject</button>
  </form>
<?php endif; ?>

<?php if (in_array($r['status'], ['pending', 'confirmed'], true)): ?>
  <h2>Cancel</h2>
  <form method="post" onsubmit="return confirm('Cancel this reservation?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="cancel">
    <p><input name="reason" placeholder="Reason" maxlength="500" size="50" required></p>
    <button type="submit">Cancel reservation</button>
  </form>
<?php endif; ?>
</body>
</html>
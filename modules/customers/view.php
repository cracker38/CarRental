<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
start_session();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Invalid customer ID.'); }

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['active', 'inactive', 'blacklisted'], true)) {
        $pdo->prepare("UPDATE customers SET status = ? WHERE customer_id = ?")
            ->execute([$newStatus, $id]);
        flash('Customer status updated to ' . $newStatus . '.');
    }
    redirect('view.php?id=' . $id);
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { http_response_code(404); die('Customer not found.'); }

$stmt = $pdo->prepare(
    "SELECT r.reservation_id, r.reservation_code, r.pickup_datetime, r.return_datetime,
            r.status, r.estimated_amount, v.make, v.model, v.registration_no
     FROM reservations r
     JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     WHERE r.customer_id = ?
     ORDER BY r.created_at DESC"
);
$stmt->execute([$id]);
$history = $stmt->fetchAll();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Customer <?= e($c['customer_code']) ?></title></head>
<body>
<p><a href="index.php">&larr; Back to customers</a></p>
<h1><?= e($c['first_name'] . ' ' . $c['last_name']) ?> (<?= e($c['customer_code']) ?>)</h1>
<?php if ($flash): ?><p style="color:green"><?= e($flash) ?></p><?php endif; ?>
<?php if ($c['status'] === 'blacklisted'): ?>
  <p style="color:red"><strong>This customer is blacklisted and cannot make reservations.</strong></p>
<?php endif; ?>

<table border="1" cellpadding="6">
  <tr><th>Status</th><td><?= e($c['status']) ?></td></tr>
  <tr><th>Phone</th><td><?= e($c['phone']) ?></td></tr>
  <tr><th>Email</th><td><?= e($c['email']) ?></td></tr>
  <tr><th>ID</th><td><?= e($c['identification_type']) ?>: <?= e($c['identification_number']) ?></td></tr>
  <tr><th>Driving license</th><td><?= e($c['driving_license_number']) ?> (expires <?= e($c['license_expiry_date']) ?>)</td></tr>
  <tr><th>Date of birth</th><td><?= e($c['date_of_birth']) ?></td></tr>
  <tr><th>Address</th><td><?= e(trim(($c['address_line'] ?? '') . ', ' . ($c['city'] ?? ''), ', ')) ?></td></tr>
  <tr><th>Registered</th><td><?= e($c['created_at']) ?></td></tr>
</table>

<p><a href="edit.php?id=<?= (int)$c['customer_id'] ?>">Edit details</a></p>

<?php if ($c['status'] === 'active'): ?>
  <p><a href="../reservations/search.php?customer_id=<?= (int)$c['customer_id'] ?>">Make a reservation</a></p>
<?php endif; ?>

<h2>Change status</h2>
<form method="post" onsubmit="return confirm('Change this customer\'s status?');">
  <?= csrf_field() ?>
  <select name="status">
    <?php foreach (['active', 'inactive', 'blacklisted'] as $s): ?>
      <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Update status</button>
</form>

<h2>Reservation history</h2>
<table border="1" cellpadding="6">
  <tr><th>Code</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Amount</th><th>Status</th></tr>
  <?php foreach ($history as $r): ?>
  <tr>
    <td><?= e($r['reservation_code']) ?></td>
    <td><?= e($r['make'] . ' ' . $r['model'] . ' (' . $r['registration_no'] . ')') ?></td>
    <td><?= e($r['pickup_datetime']) ?></td>
    <td><?= e($r['return_datetime']) ?></td>
    <td><?= e(number_format((float)$r['estimated_amount'])) ?></td>
    <td><?= e($r['status']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$history): ?><tr><td colspan="6">No reservations yet.</td></tr><?php endif; ?>
</table>
</body>
</html>
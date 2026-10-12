<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
start_session();

$statuses = ['pending', 'confirmed', 'cancelled', 'rejected', 'completed', 'no_show'];
$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT r.reservation_id, r.reservation_code, r.pickup_datetime, r.return_datetime,
               r.status, r.estimated_amount,
               c.customer_id, c.first_name, c.last_name,
               v.make, v.model, v.registration_no
        FROM reservations r
        JOIN customers c ON c.customer_id = r.customer_id
        JOIN vehicles v ON v.vehicle_id = r.vehicle_id
        WHERE 1 = 1";
$params = [];

if (in_array($status, $statuses, true)) {
    $sql .= " AND r.status = :status";
    $params['status'] = $status;
}
if ($q !== '') {
    $sql .= " AND (r.reservation_code LIKE :q1
                OR c.first_name LIKE :q2 OR c.last_name LIKE :q3
                OR CONCAT(c.first_name, ' ', c.last_name) LIKE :q4
                OR v.registration_no LIKE :q5)";
    for ($i = 1; $i <= 5; $i++) $params["q$i"] = "%$q%";
}
$sql .= " ORDER BY r.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Reservations</title></head>
<body>
<h1>Reservations</h1>
<?php if ($flash): ?><p style="color:green"><?= e($flash) ?></p><?php endif; ?>

<form method="get">
  <input name="q" placeholder="Code, customer or plate" value="<?= e($q) ?>">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <button>Filter</button>
  <a href="search.php">+ New reservation</a> |
  <a href="../customers/index.php">Customers</a>
</form>

<table border="1" cellpadding="6">
  <tr><th>Code</th><th>Customer</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Amount</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['reservation_code']) ?></td>
    <td><a href="../customers/view.php?id=<?= (int)$r['customer_id'] ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a></td>
    <td><?= e($r['make'] . ' ' . $r['model'] . ' (' . $r['registration_no'] . ')') ?></td>
    <td><?= e($r['pickup_datetime']) ?></td>
    <td><?= e($r['return_datetime']) ?></td>
    <td><?= number_format((float)$r['estimated_amount']) ?></td>
    <td><?= e($r['status']) ?></td>
    <td><a href="view.php?id=<?= (int)$r['reservation_id'] ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8">No reservations found.</td></tr><?php endif; ?>
</table>
</body>
</html>
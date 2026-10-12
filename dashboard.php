<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();

$count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$stats = [
  'Active customers'      => $count("SELECT COUNT(*) FROM customers WHERE status = 'active'"),
  'Pending reservations'  => $count("SELECT COUNT(*) FROM reservations WHERE status = 'pending'"),
  'Confirmed reservations'=> $count("SELECT COUNT(*) FROM reservations WHERE status = 'confirmed' AND return_datetime > NOW()"),
  'Vehicles in fleet'     => $count("SELECT COUNT(*) FROM vehicles WHERE status NOT IN ('inactive')"),
];

$upcoming = $pdo->query(
  "SELECT r.reservation_id, r.reservation_code, r.pickup_datetime, c.first_name, c.last_name,
          v.make, v.model
   FROM reservations r
   JOIN customers c ON c.customer_id = r.customer_id
   JOIN vehicles v ON v.vehicle_id = r.vehicle_id
   WHERE r.status = 'confirmed' AND r.pickup_datetime >= NOW()
   ORDER BY r.pickup_datetime LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Dashboard</h1>

<div class="stats">
  <?php foreach ($stats as $label => $n): ?>
    <div class="stat"><div class="num"><?= $n ?></div><div class="label"><?= e($label) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="actions">
  <a class="btn" href="modules/reservations/search.php">+ New reservation</a>
  <a class="btn" href="modules/customers/register.php">+ New customer</a>
</div>

<h2>Next confirmed pickups</h2>
<table>
  <tr><th>Code</th><th>Customer</th><th>Vehicle</th><th>Pickup</th></tr>
  <?php foreach ($upcoming as $u): ?>
  <tr>
    <td><a href="modules/reservations/view.php?id=<?= (int)$u['reservation_id'] ?>"><?= e($u['reservation_code']) ?></a></td>
    <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
    <td><?= e($u['make'] . ' ' . $u['model']) ?></td>
    <td><?= e($u['pickup_datetime']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$upcoming): ?><tr><td colspan="4">No upcoming confirmed pickups.</td></tr><?php endif; ?>
</table>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
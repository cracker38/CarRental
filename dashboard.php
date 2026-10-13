<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/reservation_api.php';
start_session();

$count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$awaitingAgreement = count(reservations_ready_for_agreement($pdo));
$stats = [
  ['Active customers',     $count("SELECT COUNT(*) FROM customers WHERE status = 'active'"), ''],
  ['Pending reservations', $count("SELECT COUNT(*) FROM reservations WHERE status = 'pending'"), 'amber'],
  ['Confirmed (upcoming)', $count("SELECT COUNT(*) FROM reservations WHERE status = 'confirmed' AND return_datetime > NOW()"), 'green'],
  ['Awaiting agreement',   $awaitingAgreement, 'amber'],
  ['Vehicles in fleet',    $count("SELECT COUNT(*) FROM vehicles WHERE status <> 'inactive'"), 'red'],
];

$sqlBase = "SELECT r.reservation_id, r.reservation_code, r.pickup_datetime, r.created_at,
                   c.first_name, c.last_name, v.make, v.model
            FROM reservations r
            JOIN customers c ON c.customer_id = r.customer_id
            JOIN vehicles v ON v.vehicle_id = r.vehicle_id";
$upcoming = $pdo->query($sqlBase . " WHERE r.status = 'confirmed' AND r.pickup_datetime >= NOW() ORDER BY r.pickup_datetime LIMIT 5")->fetchAll();
$pending  = $pdo->query($sqlBase . " WHERE r.status = 'pending' ORDER BY r.created_at DESC LIMIT 5")->fetchAll();
$notes    = $pdo->query("SELECT subject, message, created_at, related_entity_id, related_entity_type
                         FROM notifications ORDER BY created_at DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Dashboard</h1>

<div class="stats">
  <?php foreach ($stats as [$label, $n, $color]): ?>
    <div class="stat <?= $color ?>"><div class="num"><?= $n ?></div><div class="label"><?= e($label) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="actions" style="margin-bottom:22px">
  <a class="btn" href="modules/reservations/search.php">+ New reservation</a>
  <a class="btn btn-green" href="modules/customers/register.php">+ New customer</a>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head">Next confirmed pickups <a href="modules/reservations/index.php?status=confirmed">View all</a></div>
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
  </div>

  <div class="panel">
    <div class="panel-head">Waiting for confirmation <a href="modules/reservations/index.php?status=pending">View all</a></div>
    <table>
      <tr><th>Code</th><th>Customer</th><th>Vehicle</th><th>Requested</th></tr>
      <?php foreach ($pending as $p): ?>
      <tr>
        <td><a href="modules/reservations/view.php?id=<?= (int)$p['reservation_id'] ?>"><?= e($p['reservation_code']) ?></a></td>
        <td><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
        <td><?= e($p['make'] . ' ' . $p['model']) ?></td>
        <td><?= e($p['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$pending): ?><tr><td colspan="4">Nothing is waiting.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<div class="panel" style="margin-top:18px">
  <div class="panel-head">Recent notifications <a href="modules/notifications/index.php">View all</a></div>
  <?php if ($notes): ?>
  <ul>
    <?php foreach ($notes as $n): ?>
    <li>
      <?php if ($n['related_entity_type'] === 'reservation'): ?>
        <a href="modules/reservations/view.php?id=<?= (int)$n['related_entity_id'] ?>"><strong><?= e($n['subject']) ?></strong></a>
      <?php else: ?>
        <strong><?= e($n['subject']) ?></strong>
      <?php endif; ?>
      <small><?= e($n['message']) ?> &middot; <?= e($n['created_at']) ?></small>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?><div class="empty">No notifications yet.</div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
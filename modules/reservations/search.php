<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
date_default_timezone_set('Africa/Kigali');
start_session();

$errors = [];
$vehicles = [];
$searched = false;
$pickup = $_GET['pickup'] ?? '';
$return = $_GET['return'] ?? '';
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$customerId = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT) ?: 0;

$categories = $pdo->query(
    "SELECT category_id, category_name FROM vehicle_categories WHERE is_active = 1 ORDER BY category_name"
)->fetchAll();

function parse_dt(string $v): ?DateTime {
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v);
    return ($d && $d->format('Y-m-d\TH:i') === $v) ? $d : null;
}

if ($pickup !== '' || $return !== '') {
    $searched = true;
    $p = parse_dt($pickup);
    $r = parse_dt($return);
    $now = new DateTime();

    if (!$p || !$r) {
        $errors[] = 'Enter a valid pickup and return date and time.';
    } else {
        if ($p < (clone $now)->modify('-5 minutes')) $errors[] = 'Pickup cannot be in the past.';
        if ($r <= $p) $errors[] = 'Return must be after pickup.';
        if ($r > (clone $p)->modify('+90 days')) $errors[] = 'Maximum rental period is 90 days.';
    }

    if (!$errors) {
        $pk = $p->format('Y-m-d H:i:s');
        $rt = $r->format('Y-m-d H:i:s');

        $sql = "SELECT v.vehicle_id, v.registration_no, v.make, v.model, v.manufacture_year,
                       v.transmission, v.fuel_type, v.passenger_capacity, v.daily_rate,
                       c.category_name
                FROM vehicles v
                JOIN vehicle_categories c ON c.category_id = v.category_id
                WHERE v.status NOT IN ('maintenance','inactive')
                  AND NOT EXISTS (
                      SELECT 1 FROM reservations rs
                      WHERE rs.vehicle_id = v.vehicle_id AND rs.status = 'confirmed'
                        AND rs.pickup_datetime < :ret1 AND rs.return_datetime > :pick1)
                  AND NOT EXISTS (
                      SELECT 1 FROM rentals rn
                      WHERE rn.vehicle_id = v.vehicle_id AND rn.status IN ('active','overdue')
                        AND rn.expected_return_datetime > :pick2)
                  AND NOT EXISTS (
                      SELECT 1 FROM maintenance_records m
                      WHERE m.vehicle_id = v.vehicle_id AND m.status IN ('scheduled','in_progress')
                        AND m.start_date <= DATE(:ret2)
                        AND COALESCE(m.completed_date, m.expected_completion_date, '9999-12-31') >= DATE(:pick3))";
        $params = ['ret1' => $rt, 'pick1' => $pk, 'pick2' => $pk, 'ret2' => $rt, 'pick3' => $pk];

        if ($category) {
            $sql .= " AND v.category_id = :cat";
            $params['cat'] = $category;
        }
        $sql .= " ORDER BY v.daily_rate, v.make, v.model";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vehicles = $stmt->fetchAll();

        $days = max(1, ceil(($r->getTimestamp() - $p->getTimestamp()) / 86400));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Vehicle Availability</title></head>
<body>
<h1>Find Available Vehicles</h1>

<?php foreach ($errors as $err): ?><p style="color:red"><?= e($err) ?></p><?php endforeach; ?>

<form method="get">
  <?php if ($customerId): ?><input type="hidden" name="customer_id" value="<?= $customerId ?>"><?php endif; ?>
  <p><label>Pickup <input type="datetime-local" name="pickup" value="<?= e($pickup) ?>" required></label></p>
  <p><label>Return <input type="datetime-local" name="return" value="<?= e($return) ?>" required></label></p>
  <p><label>Category
    <select name="category">
      <option value="0">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['category_id'] ?>" <?= $category === (int)$c['category_id'] ? 'selected' : '' ?>>
          <?= e($c['category_name']) ?></option>
      <?php endforeach; ?>
    </select></label></p>
  <button type="submit">Search</button>
</form>

<?php if ($searched && !$errors): ?>
  <h2><?= count($vehicles) ?> vehicle(s) available (<?= (int)$days ?> day(s))</h2>
  <table border="1" cellpadding="6">
    <tr><th>Vehicle</th><th>Category</th><th>Reg. no</th><th>Seats</th><th>Daily rate</th><th>Estimated total</th><th></th></tr>
    <?php foreach ($vehicles as $v): ?>
    <tr>
      <td><?= e($v['make'] . ' ' . $v['model'] . ' ' . ($v['manufacture_year'] ?? '')) ?></td>
      <td><?= e($v['category_name']) ?></td>
      <td><?= e($v['registration_no']) ?></td>
      <td><?= e((string)$v['passenger_capacity']) ?></td>
      <td><?= number_format((float)$v['daily_rate']) ?></td>
      <td><?= number_format((float)$v['daily_rate'] * $days) ?></td>
      <td><a href="create.php?vehicle_id=<?= (int)$v['vehicle_id'] ?>&pickup=<?= urlencode($pickup) ?>&return=<?= urlencode($return) ?><?= $customerId ? '&customer_id=' . $customerId : '' ?>">Reserve</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$vehicles): ?><tr><td colspan="7">No vehicles available for these dates.</td></tr><?php endif; ?>
  </table>
<?php endif; ?>
</body>
</html>
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/availability.php';
date_default_timezone_set('Africa/Kigali');
start_session();

function parse_dt(string $v): ?DateTime {
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v);
    return ($d && $d->format('Y-m-d\TH:i') === $v) ? $d : null;
}

$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$vehicleId  = filter_var($in['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
$customerId = filter_var($in['customer_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$pickup = trim($in['pickup'] ?? '');
$return = trim($in['return'] ?? '');

if (!$vehicleId) { http_response_code(400); die('Invalid vehicle.'); }

$stmt = $pdo->prepare(
    "SELECT v.*, c.category_name FROM vehicles v
     JOIN vehicle_categories c ON c.category_id = v.category_id
     WHERE v.vehicle_id = ?"
);
$stmt->execute([$vehicleId]);
$vehicle = $stmt->fetch();
if (!$vehicle) { http_response_code(404); die('Vehicle not found.'); }

$customers = $pdo->query(
    "SELECT customer_id, customer_code, first_name, last_name FROM customers
     WHERE status = 'active' ORDER BY first_name, last_name LIMIT 500"
)->fetchAll();

$deposit = (float)($pdo->query(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'default_deposit_amount'"
)->fetchColumn() ?: 0);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

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

    // Customer checks
    $customer = null;
    if (!$customerId) {
        $errors[] = 'Select a customer.';
    } else {
        $s = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $s->execute([$customerId]);
        $customer = $s->fetch();
        if (!$customer) {
            $errors[] = 'Customer not found.';
        } elseif ($customer['status'] !== 'active') {
            $errors[] = 'This customer is ' . $customer['status'] . ' and cannot make reservations.';
        } elseif ($customer['license_expiry_date'] && $r && $customer['license_expiry_date'] < $r->format('Y-m-d')) {
            $errors[] = 'The customer\'s driving license expires before the return date.';
        }
    }

    if (!$errors) {
        $pk = $p->format('Y-m-d H:i:s');
        $rt = $r->format('Y-m-d H:i:s');
        $days = max(1, (int)ceil(($r->getTimestamp() - $p->getTimestamp()) / 86400));

        try {
            $pdo->beginTransaction();

            // Lock the vehicle row so the availability check and insert are consistent
            $pdo->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? FOR UPDATE")->execute([$vehicleId]);

            if (!vehicle_is_available($pdo, $vehicleId, $pk, $rt)) {
                $pdo->rollBack();
                $errors[] = 'This vehicle is not available for the selected period.';
            } else {
                $rate = (float)$vehicle['daily_rate']; // copied so later price changes don't alter this booking
                $pdo->prepare(
                    "INSERT INTO reservations
                     (reservation_code, customer_id, vehicle_id, pickup_datetime, return_datetime,
                      pickup_location, return_location, daily_rate, estimated_days, estimated_amount,
                      security_deposit, customer_notes, created_by)
                     VALUES (:code, :cid, :vid, :pk, :rt, :pl, :rl, :rate, :days, :amt, :dep, :notes, :by)"
                )->execute([
                    'code' => 'TMP' . bin2hex(random_bytes(8)),
                    'cid' => $customerId, 'vid' => $vehicleId, 'pk' => $pk, 'rt' => $rt,
                    'pl' => nullable($in['pickup_location'] ?? ''),
                    'rl' => nullable($in['return_location'] ?? ''),
                    'rate' => $rate, 'days' => $days, 'amt' => $rate * $days,
                    'dep' => $deposit,
                    'notes' => nullable($in['customer_notes'] ?? ''),
                    'by' => current_user_id(),
                ]);
                $rid = (int)$pdo->lastInsertId();
                $code = sprintf('RES-%s-%05d', date('Ymd'), $rid);
                $pdo->prepare("UPDATE reservations SET reservation_code = ? WHERE reservation_id = ?")
                    ->execute([$code, $rid]);

                // In-app notification
                $pdo->prepare(
                    "INSERT INTO notifications
                     (customer_id, channel, notification_type, subject, message,
                      related_entity_type, related_entity_id, status)
                     VALUES (?, 'in_app', 'reservation_created', 'Reservation received', ?, 'reservation', ?, 'queued')"
                )->execute([
                    $customerId,
                    "Your reservation $code for {$vehicle['make']} {$vehicle['model']} is pending confirmation.",
                    $rid,
                ]);

                $pdo->commit();
                flash("Reservation $code created (pending confirmation).");
                redirect('../customers/view.php?id=' . $customerId);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $errors[] = 'Could not save the reservation. Please try again.';
        }
    }
}

// Preview of cost for display
$previewDays = null;
if (($p2 = parse_dt($pickup)) && ($r2 = parse_dt($return)) && $r2 > $p2) {
    $previewDays = max(1, (int)ceil(($r2->getTimestamp() - $p2->getTimestamp()) / 86400));
}
?>
<?php $pageTitle = 'New reservation'; require_once __DIR__ . '/../../includes/header.php'; ?>
<p><a href="search.php?pickup=<?= urlencode($pickup) ?>&return=<?= urlencode($return) ?>">&larr; Back to search</a></p>
<h1>New Reservation</h1>

<?php foreach ($errors as $err): ?><p style="color:red"><?= e($err) ?></p><?php endforeach; ?>

<h3><?= e($vehicle['make'] . ' ' . $vehicle['model']) ?> (<?= e($vehicle['registration_no']) ?>)</h3>
<p>Category: <?= e($vehicle['category_name']) ?> | Daily rate: <?= number_format((float)$vehicle['daily_rate']) ?> RWF</p>
<?php if ($previewDays): ?>
  <p>Estimated: <?= $previewDays ?> day(s) &times; <?= number_format((float)$vehicle['daily_rate']) ?>
     = <strong><?= number_format((float)$vehicle['daily_rate'] * $previewDays) ?> RWF</strong>
     <?php if ($deposit > 0): ?>(+ deposit <?= number_format($deposit) ?>)<?php endif; ?></p>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="vehicle_id" value="<?= (int)$vehicleId ?>">
  <p><label>Customer
    <select name="customer_id" required>
      <option value="">-- select customer --</option>
      <?php foreach ($customers as $c): ?>
        <option value="<?= (int)$c['customer_id'] ?>" <?= $customerId === (int)$c['customer_id'] ? 'selected' : '' ?>>
          <?= e($c['customer_code'] . ' - ' . $c['first_name'] . ' ' . $c['last_name']) ?></option>
      <?php endforeach; ?>
    </select></label>
    <a href="../customers/register.php">+ New customer</a></p>
  <p><label>Pickup <input type="datetime-local" name="pickup" value="<?= e($pickup) ?>" required></label></p>
  <p><label>Return <input type="datetime-local" name="return" value="<?= e($return) ?>" required></label></p>
  <p><label>Pickup location <input name="pickup_location" value="<?= e($in['pickup_location'] ?? '') ?>"></label></p>
  <p><label>Return location <input name="return_location" value="<?= e($in['return_location'] ?? '') ?>"></label></p>
  <p><label>Notes <br><textarea name="customer_notes" rows="3" cols="40"><?= e($in['customer_notes'] ?? '') ?></textarea></label></p>
  <button type="submit">Create reservation</button>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


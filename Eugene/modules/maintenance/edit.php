<?php
// modules/maintenance/edit.php  (Member 5: Eugenie)
// Edit details, move status scheduled -> in_progress -> completed (or cancelled).
   require_once __DIR__ . '/../../config/db.php';
require_maintenance_permission();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare(
    "SELECT m.*, v.registration_no, v.make, v.model
     FROM maintenance_records m JOIN vehicles v ON v.vehicle_id = m.vehicle_id
     WHERE m.maintenance_id = ?"
);
$st->execute([$id]);
$rec = $st->fetch();
if (!$rec) {
    http_response_code(404);
    exit('Maintenance record not found.');
}

// Allowed next statuses from the current one.
$transitions = [
    'scheduled'   => ['scheduled', 'in_progress', 'cancelled'],
    'in_progress' => ['in_progress', 'completed', 'cancelled'],
    'completed'   => ['completed'],
    'cancelled'   => ['cancelled'],
];
$locked = in_array($rec['status'], ['completed', 'cancelled'], true);

$errors = [];
$f = $rec;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    csrf_check();
    foreach (['maintenance_type', 'title', 'description', 'service_provider', 'start_date',
              'expected_completion_date', 'completed_date', 'cost', 'next_service_date',
              'odometer_reading', 'status', 'notes'] as $k) {
        $f[$k] = trim($_POST[$k] ?? '');
    }

    if (!array_key_exists($f['maintenance_type'], MAINTENANCE_TYPES)) $errors[] = 'Invalid maintenance type.';
    if ($f['title'] === '')                                           $errors[] = 'Title is required.';
    if (!in_array($f['status'], $transitions[$rec['status']], true))  $errors[] = 'That status change is not allowed.';
    if (!is_numeric($f['cost']) || (float)$f['cost'] < 0)             $errors[] = 'Cost must be 0 or more.';
    if ($f['expected_completion_date'] !== '' && $f['expected_completion_date'] < $f['start_date'])
        $errors[] = 'Expected completion cannot be before the start date.';
    if ($f['odometer_reading'] !== '' && (!ctype_digit($f['odometer_reading'])))
        $errors[] = 'Odometer must be a whole number.';

    if ($f['status'] === 'completed') {
        if ($f['completed_date'] === '') $f['completed_date'] = date('Y-m-d');
        if ($f['completed_date'] < $f['start_date']) $errors[] = 'Completed date cannot be before the start date.';
    } else {
        $f['completed_date'] = '';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the row so two people cannot change it at the same time.
            $lock = $pdo->prepare("SELECT status FROM maintenance_records WHERE maintenance_id = ? FOR UPDATE");
            $lock->execute([$id]);
            $currentStatus = $lock->fetchColumn();
            if (!in_array($f['status'], $transitions[$currentStatus], true)) {
                throw new RuntimeException('This record was changed by someone else. Reload the page.');
            }

            $up = $pdo->prepare(
                "UPDATE maintenance_records SET
                   maintenance_type = ?, title = ?, description = ?, service_provider = ?,
                   start_date = ?, expected_completion_date = ?, completed_date = ?,
                   odometer_reading = ?, cost = ?, next_service_date = ?, status = ?, notes = ?
                 WHERE maintenance_id = ?"
            );
            $up->execute([
                $f['maintenance_type'], $f['title'], $f['description'] ?: null, $f['service_provider'] ?: null,
                $f['start_date'], $f['expected_completion_date'] ?: null, $f['completed_date'] ?: null,
                $f['odometer_reading'] !== '' ? (int)$f['odometer_reading'] : null,
                (float)$f['cost'], $f['next_service_date'] ?: null, $f['status'], $f['notes'] ?: null, $id,
            ]);

            if ($f['status'] !== $currentStatus) {
                sync_vehicle_status($pdo, (int)$rec['vehicle_id'], $f['status'], $id);
            }

            // When finished, update the vehicle's mileage if a reading was given.
            if ($f['status'] === 'completed' && $f['odometer_reading'] !== '') {
                $pdo->prepare("UPDATE vehicles SET current_mileage = GREATEST(current_mileage, ?) WHERE vehicle_id = ?")
                    ->execute([(int)$f['odometer_reading'], (int)$rec['vehicle_id']]);
            }

            $pdo->commit();
            $_SESSION['flash'] = "Maintenance {$rec['maintenance_number']} updated.";
            header('Location: list.php');
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not save the record.';
        }
    }
}

page_header('Maintenance ' . $rec['maintenance_number']);
?>
  <h1 class="h3 mb-1"><?= e($rec['maintenance_number']) ?> <?= status_badge($rec['status']) ?></h1>
  <p class="text-muted"><?= e($rec['registration_no'] . ' - ' . $rec['make'] . ' ' . $rec['model']) ?></p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
  <?php endforeach; ?>
  <?php if ($locked): ?>
    <div class="alert alert-info">This record is <?= e($rec['status']) ?> and can no longer be edited.</div>
  <?php endif; ?>

  <form method="post" class="card card-body">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <fieldset <?= $locked ? 'disabled' : '' ?>>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Type</label>
        <select name="maintenance_type" class="form-select">
          <?php foreach (MAINTENANCE_TYPES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $f['maintenance_type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach ($transitions[$rec['status']] as $s): ?>
            <option value="<?= e($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Title</label>
        <input name="title" class="form-control" maxlength="180" required value="<?= e($f['title']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($f['description']) ?></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Service provider</label>
        <input name="service_provider" class="form-control" maxlength="180" value="<?= e($f['service_provider']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Cost (RWF)</label>
        <input type="number" step="0.01" min="0" name="cost" class="form-control" value="<?= e($f['cost']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Odometer (km)</label>
        <input type="number" min="0" name="odometer_reading" class="form-control" value="<?= e($f['odometer_reading']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Start date</label>
        <input type="date" name="start_date" class="form-control" required value="<?= e($f['start_date']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Expected completion</label>
        <input type="date" name="expected_completion_date" class="form-control" value="<?= e($f['expected_completion_date']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Completed date</label>
        <input type="date" name="completed_date" class="form-control" value="<?= e($f['completed_date']) ?>">
        <div class="form-text">Used when status is Completed (defaults to today).</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Next service date</label>
        <input type="date" name="next_service_date" class="form-control" value="<?= e($f['next_service_date']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($f['notes']) ?></textarea>
      </div>
    </div>
    </fieldset>
    <div class="mt-3">
      <?php if (!$locked): ?><button class="btn btn-primary">Save changes</button><?php endif; ?>
      <a href="list.php" class="btn btn-link">Back to list</a>
    </div>
  </form>
<?php page_footer(); ?>
<?php
// modules/maintenance/add.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_maintenance_permission();

$errors = [];
$old = [
    'vehicle_id' => '', 'maintenance_type' => 'routine_service', 'title' => '', 'description' => '',
    'service_provider' => '', 'start_date' => date('Y-m-d'), 'expected_completion_date' => '',
    'cost' => '0', 'next_service_date' => '', 'status' => 'scheduled', 'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    $vehicleId = (int)$old['vehicle_id'];
    if ($vehicleId <= 0)                                              $errors[] = 'Select a vehicle.';
    if (!array_key_exists($old['maintenance_type'], MAINTENANCE_TYPES)) $errors[] = 'Invalid maintenance type.';
    if ($old['title'] === '')                                         $errors[] = 'Title is required.';
    if (!in_array($old['status'], ['scheduled', 'in_progress'], true)) $errors[] = 'A new record can only be scheduled or in progress.';
    if (!DateTime::createFromFormat('Y-m-d', $old['start_date']))     $errors[] = 'Start date is invalid.';
    if ($old['expected_completion_date'] !== '' &&
        $old['expected_completion_date'] < $old['start_date'])        $errors[] = 'Expected completion cannot be before the start date.';
    if (!is_numeric($old['cost']) || (float)$old['cost'] < 0)         $errors[] = 'Cost must be 0 or more.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $chk = $pdo->prepare("SELECT status FROM vehicles WHERE vehicle_id = ? FOR UPDATE");
            $chk->execute([$vehicleId]);
            $vStatus = $chk->fetchColumn();
            if ($vStatus === false || $vStatus === 'inactive') {
                throw new RuntimeException('Vehicle not found or inactive.');
            }

            $number = next_maintenance_number($pdo);
            $ins = $pdo->prepare(
                "INSERT INTO maintenance_records
                 (maintenance_number, vehicle_id, reported_by, maintenance_type, title, description,
                  service_provider, start_date, expected_completion_date, cost, next_service_date, status, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $number, $vehicleId, $_SESSION['user_id'] ?? null, $old['maintenance_type'], $old['title'],
                $old['description'] ?: null, $old['service_provider'] ?: null, $old['start_date'],
                $old['expected_completion_date'] ?: null, (float)$old['cost'],
                $old['next_service_date'] ?: null, $old['status'], $old['notes'] ?: null,
            ]);
            $newId = (int)$pdo->lastInsertId();

            sync_vehicle_status($pdo, $vehicleId, $old['status'], $newId);

            $pdo->commit();
            $_SESSION['flash'] = "Maintenance $number created.";
            header('Location: list.php');
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not save the record.';
        }
    }
}

$vehicles = $pdo->query(
    "SELECT vehicle_id, registration_no, make, model, status FROM vehicles
     WHERE status <> 'inactive' ORDER BY registration_no"
)->fetchAll();

page_header('New maintenance');
?>
  <h1 class="h3 mb-3">New maintenance</h1>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post" class="card card-body">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Vehicle *</label>
        <select name="vehicle_id" class="form-select" required>
          <option value="">Select vehicle</option>
          <?php foreach ($vehicles as $v): ?>
            <option value="<?= (int)$v['vehicle_id'] ?>" <?= (int)$old['vehicle_id'] === (int)$v['vehicle_id'] ? 'selected' : '' ?>>
              <?= e($v['registration_no'] . ' - ' . $v['make'] . ' ' . $v['model'] . ' (' . $v['status'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Type *</label>
        <select name="maintenance_type" class="form-select">
          <?php foreach (MAINTENANCE_TYPES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $old['maintenance_type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Title *</label>
        <input name="title" class="form-control" maxlength="180" required value="<?= e($old['title']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($old['description']) ?></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Service provider</label>
        <input name="service_provider" class="form-control" maxlength="180" value="<?= e($old['service_provider']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Estimated cost (RWF)</label>
        <input type="number" step="0.01" min="0" name="cost" class="form-control" value="<?= e($old['cost']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Start date *</label>
        <input type="date" name="start_date" class="form-control" required value="<?= e($old['start_date']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Expected completion</label>
        <input type="date" name="expected_completion_date" class="form-control" value="<?= e($old['expected_completion_date']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Next service date</label>
        <input type="date" name="next_service_date" class="form-control" value="<?= e($old['next_service_date']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="scheduled"   <?= $old['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
          <option value="in_progress" <?= $old['status'] === 'in_progress' ? 'selected' : '' ?>>In progress (vehicle goes to maintenance now)</option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($old['notes']) ?></textarea>
      </div>
    </div>
    <div class="mt-3">
      <button class="btn btn-primary">Save</button>
      <a href="list.php" class="btn btn-link">Cancel</a>
    </div>
  </form>
<?php page_footer(); ?>
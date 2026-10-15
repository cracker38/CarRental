<?php
// modules/maintenance/list.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_maintenance_permission();

$status = $_GET['status'] ?? '';
$params = [];
$sql = "SELECT m.maintenance_id, m.maintenance_number, m.maintenance_type, m.title, m.start_date,
               m.expected_completion_date, m.completed_date, m.cost, m.status,
               v.registration_no, v.make, v.model
        FROM maintenance_records m
        JOIN vehicles v ON v.vehicle_id = m.vehicle_id";

if (in_array($status, MAINTENANCE_STATUSES, true)) {
    $sql .= " WHERE m.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY m.start_date DESC, m.maintenance_id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

page_header('Maintenance');
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Vehicle maintenance</h1>
    <a href="add.php" class="btn btn-primary">+ New maintenance</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
  <?php endif; ?>

  <form method="get" class="row g-2 mb-3">
    <div class="col-auto">
      <select name="status" class="form-select">
        <option value="">All statuses</option>
        <?php foreach (MAINTENANCE_STATUSES as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Number</th><th>Vehicle</th><th>Type</th><th>Title</th>
            <th>Start</th><th>Expected</th><th>Completed</th><th class="text-end">Cost (RWF)</th>
            <th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="10" class="text-muted">No maintenance records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['maintenance_number']) ?></td>
            <td><?= e($r['registration_no']) ?><div class="small text-muted"><?= e($r['make'] . ' ' . $r['model']) ?></div></td>
            <td><?= e(MAINTENANCE_TYPES[$r['maintenance_type']] ?? $r['maintenance_type']) ?></td>
            <td><?= e($r['title']) ?></td>
            <td><?= e($r['start_date']) ?></td>
            <td><?= e($r['expected_completion_date'] ?? '-') ?></td>
            <td><?= e($r['completed_date'] ?? '-') ?></td>
            <td class="text-end"><?= number_format((float)$r['cost'], 2) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int)$r['maintenance_id'] ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php page_footer(); ?>
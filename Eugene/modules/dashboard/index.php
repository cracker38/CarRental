<?php
// modules/dashboard/index.php  (Member 5: Eugenie)
// AdminLTE 4 style dashboard. The layout (sidebar/top bar) comes from modules/nav.php.
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../nav.php';

if (!has_permission('reports.operational')) {
    http_response_code(403);
    exit('Access denied.');
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function rows(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
function scalar(PDO $pdo, string $sql, array $params = []) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

$root = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

$canFinance = has_permission('reports.financial');
$canMaint   = has_permission('maintenance.manage');
$canNotif   = has_permission('notifications.manage');

// ---- KPI numbers ----------------------------------------------------------
$totalVehicles = (int)scalar($pdo, "SELECT COUNT(*) FROM vehicles WHERE status <> 'inactive'");
$available     = (int)scalar($pdo, "SELECT COUNT(*) FROM vw_vehicle_current_status WHERE operational_status = 'available'");
$activeRentals = (int)scalar($pdo, "SELECT COUNT(*) FROM rentals WHERE status IN ('active','overdue')");
$overdue       = (int)scalar($pdo, "SELECT COUNT(*) FROM rentals WHERE status = 'overdue'");
$pending       = (int)scalar($pdo, "SELECT COUNT(*) FROM reservations WHERE status = 'pending'");
$inMaintenance = (int)scalar($pdo, "SELECT COUNT(*) FROM maintenance_records WHERE status IN ('scheduled','in_progress')");
$outstanding   = (float)scalar($pdo, "SELECT COALESCE(SUM(outstanding_balance),0) FROM vw_invoice_balances WHERE status NOT IN ('void','draft','paid')");
$unread        = (int)scalar($pdo, "SELECT COUNT(*) FROM notifications WHERE channel = 'in_app' AND status <> 'read'");

// [label, value, icon, colour, link|null]
$boxes = [
    ['Total vehicles',       $totalVehicles, 'bi-car-front-fill',            'primary',   $root . '/reports/utilization.php'],
    ['Available now',        $available,     'bi-check-circle-fill',         'success',   $root . '/reports/utilization.php'],
    ['Pending reservations', $pending,       'bi-hourglass-split',           'warning',   $root . '/reports/reservations.php'],
    ['Overdue rentals',      $overdue,       'bi-exclamation-triangle-fill', 'danger',    $canNotif ? $root . '/notifications/list.php' : null],
    ['Active rentals',       $activeRentals, 'bi-key-fill',                  'info',      $root . '/reports/utilization.php'],
    ['In maintenance',       $inMaintenance, 'bi-tools',                     'secondary', $canMaint ? $root . '/maintenance/list.php' : null],
];
if ($canFinance) {
    $boxes[] = ['Outstanding (RWF)', number_format($outstanding, 0), 'bi-cash-coin', 'dark', $root . '/reports/payments.php'];
}
if ($canNotif) {
    $boxes[] = ['Unread alerts', $unread, 'bi-bell-fill', 'primary', $root . '/notifications/list.php'];
}

// ---- Fleet status chart ---------------------------------------------------
$fleet = rows($pdo,
    "SELECT operational_status AS s, COUNT(*) AS n
     FROM vw_vehicle_current_status
     WHERE operational_status <> 'inactive'
     GROUP BY operational_status ORDER BY n DESC");
$fleetColours = ['available' => '#20c997', 'rented' => '#ffffff', 'reserved' => '#ffc107', 'maintenance' => '#fd7e14'];
$fleetLabels = $fleetData = $fleetColourList = [];
foreach ($fleet as $f) {
    $fleetLabels[]     = ucfirst($f['s']);
    $fleetData[]       = (int)$f['n'];
    $fleetColourList[] = $fleetColours[$f['s']] ?? '#adb5bd';
}

// ---- Revenue chart (last 6 months, finance permission only) ----------------
$revLabels = $revData = [];
if ($canFinance) {
    $first  = new DateTime(scalar($pdo, "SELECT DATE_FORMAT(CURDATE(), '%Y-%m-01')"));
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $m = (clone $first)->modify("-$i month");
        $months[$m->format('Y-m')] = ['label' => $m->format('M Y'), 'total' => 0.0];
    }
    $startKey = array_key_first($months) . '-01';
    foreach (rows($pdo,
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
         FROM payments WHERE status = 'completed' AND payment_date >= ?
         GROUP BY ym", [$startKey]) as $r) {
        if (isset($months[$r['ym']])) $months[$r['ym']]['total'] = (float)$r['total'];
    }
    foreach ($months as $m) { $revLabels[] = $m['label']; $revData[] = $m['total']; }
}

// ---- Activity -------------------------------------------------------------
$upcomingReturns = rows($pdo,
    "SELECT r.rental_number, v.registration_no, CONCAT(c.first_name,' ',c.last_name) AS customer,
            r.expected_return_datetime, r.status,
            TIMESTAMPDIFF(HOUR, NOW(), r.expected_return_datetime) AS hours_left
     FROM rentals r
     JOIN vehicles v  ON v.vehicle_id  = r.vehicle_id
     JOIN customers c ON c.customer_id = r.customer_id
     WHERE r.status IN ('active','overdue')
     ORDER BY r.expected_return_datetime ASC
     LIMIT 6");

$todaysPickups = rows($pdo,
    "SELECT rs.reservation_code, v.registration_no, CONCAT(c.first_name,' ',c.last_name) AS customer,
            DATE_FORMAT(rs.pickup_datetime, '%H:%i') AS pickup_time
     FROM reservations rs
     JOIN vehicles v  ON v.vehicle_id  = rs.vehicle_id
     JOIN customers c ON c.customer_id = rs.customer_id
     WHERE rs.status = 'confirmed' AND DATE(rs.pickup_datetime) = CURDATE()
     ORDER BY rs.pickup_datetime ASC");

$maintenanceJobs = $canMaint ? rows($pdo,
    "SELECT m.maintenance_number, m.title, m.status, m.expected_completion_date, v.registration_no
     FROM maintenance_records m JOIN vehicles v ON v.vehicle_id = m.vehicle_id
     WHERE m.status IN ('scheduled','in_progress')
     ORDER BY FIELD(m.status,'in_progress','scheduled'), m.start_date
     LIMIT 5") : [];

$latestAlerts = $canNotif ? rows($pdo,
    "SELECT notification_type, subject, created_at
     FROM notifications
     WHERE channel = 'in_app' AND status <> 'read'
     ORDER BY created_at DESC, notification_id DESC
     LIMIT 5") : [];

function when_text($hours): string {
    $h = (int)$hours;
    if ($h < 0)   { $a = abs($h); return ($a >= 24 ? intdiv($a, 24) . ' d' : $a . ' h') . ' overdue'; }
    if ($h >= 24) { return 'in ' . intdiv($h, 24) . ' d'; }
    return 'in ' . $h . ' h';
}
function alert_icon(string $t): string {
    if (strpos($t, 'overdue') !== false) return 'bi-exclamation-octagon-fill text-danger';
    if (strpos($t, 'payment') !== false) return 'bi-cash-coin text-warning';
    if (strpos($t, 'return')  !== false) return 'bi-arrow-return-left text-info';
    return 'bi-calendar-check-fill text-primary';
}

// Small reusable pieces (the card header with the collapse button used by AdminLTE)
function card_open(string $title, string $extra = '', string $class = ''): void { ?>
  <div class="card <?= e($class) ?> mb-4">
    <div class="card-header">
      <h3 class="card-title"><?= e($title) ?></h3>
      <div class="card-tools">
        <?= $extra ?>
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
          <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
          <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
        </button>
      </div>
    </div>
<?php }
function card_close(): void { echo '</div>'; }

function returns_card(array $upcomingReturns): void {
    card_open('Upcoming returns');
    if ($upcomingReturns): ?>
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Rental</th><th>Vehicle</th><th>Customer</th><th>Due</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($upcomingReturns as $r): $late = $r['status'] === 'overdue' || (int)$r['hours_left'] < 0; ?>
          <tr class="<?= $late ? 'table-danger' : '' ?>">
            <td><?= e($r['rental_number']) ?></td>
            <td><?= e($r['registration_no']) ?></td>
            <td><?= e($r['customer']) ?></td>
            <td class="text-nowrap"><?= e($r['expected_return_datetime']) ?></td>
            <td><span class="badge text-bg-<?= $late ? 'danger' : 'info' ?>"><?= e(when_text($r['hours_left'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="empty"><i class="bi bi-calendar2-check"></i>No active rentals.</div>
    <?php endif;
    card_close();
}

layout_head('Dashboard', 'Dashboard');
?>

<!-- Info boxes (small boxes) -->
<div class="row">
  <?php foreach ($boxes as [$label, $value, $icon, $colour, $link]):
      $dark = ($colour === 'warning' || $colour === 'info'); // better contrast on light colours
      $linkClass = $dark ? 'link-dark' : 'link-light';
  ?>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-<?= e($colour) ?>">
        <div class="inner">
          <h3><?= e($value) ?></h3>
          <p><?= e($label) ?></p>
        </div>
        <i class="bi <?= e($icon) ?> small-box-icon-i"></i>
        <?php if ($link): ?>
          <a href="<?= e($link) ?>" class="small-box-footer <?= $linkClass ?> link-underline-opacity-0 link-underline-opacity-50-hover">
            More info <i class="bi bi-link-45deg"></i>
          </a>
        <?php else: ?>
          <span class="small-box-footer <?= $linkClass ?>">&nbsp;</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row">
  <div class="col-lg-7">
    <?php if ($canFinance): ?>
      <?php card_open('Revenue, last 6 months (RWF)', '<a class="btn btn-sm btn-outline-primary me-1" href="' . e($root) . '/reports/revenue.php">Full report</a>'); ?>
        <div class="card-body">
          <?php if (array_sum($revData) > 0): ?>
            <div class="chart-box"><canvas id="revenueChart"></canvas></div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-graph-up"></i>No completed payments in the last 6 months.</div>
          <?php endif; ?>
        </div>
      <?php card_close(); ?>
    <?php else: ?>
      <?php returns_card($upcomingReturns); ?>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="card text-bg-primary border-0 mb-4">
      <div class="card-header border-0">
        <h3 class="card-title">Fleet status</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool text-white" data-lte-toggle="card-collapse">
            <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
            <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <?php if ($fleetData): ?>
          <div class="chart-box"><canvas id="fleetChart"></canvas></div>
        <?php else: ?>
          <div class="empty text-white-50"><i class="bi bi-car-front"></i>No vehicles yet.</div>
        <?php endif; ?>
      </div>
      <div class="card-footer border-0 bg-transparent text-white-50 small">
        <?= (int)$available ?> of <?= (int)$totalVehicles ?> vehicles available now
      </div>
    </div>
  </div>
</div>

<!-- Activity -->
<div class="row">
  <div class="col-lg-7">
    <?php if ($canFinance) { returns_card($upcomingReturns); } ?>
    <?php if ($canMaint): ?>
      <?php card_open('Maintenance', '<a class="btn btn-sm btn-outline-secondary me-1" href="' . e($root) . '/maintenance/list.php">View all</a>'); ?>
        <?php if ($maintenanceJobs): ?>
          <ul class="list-group list-group-flush">
          <?php foreach ($maintenanceJobs as $m): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold"><?= e($m['registration_no']) ?> &middot; <?= e($m['title']) ?></div>
                <div class="small text-muted"><?= e($m['maintenance_number']) ?>
                  <?= $m['expected_completion_date'] ? ' &middot; due ' . e($m['expected_completion_date']) : '' ?></div>
              </div>
              <span class="badge text-bg-<?= $m['status'] === 'in_progress' ? 'warning' : 'secondary' ?>"><?= e(str_replace('_', ' ', $m['status'])) ?></span>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty"><i class="bi bi-wrench-adjustable"></i>No open maintenance jobs.</div>
        <?php endif; ?>
      <?php card_close(); ?>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <?php card_open('Today\'s pickups', '<span class="badge text-bg-primary me-2">' . (int)count($todaysPickups) . '</span>'); ?>
      <?php if ($todaysPickups): ?>
        <ul class="list-group list-group-flush">
        <?php foreach ($todaysPickups as $p): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= e($p['customer']) ?></div>
              <div class="small text-muted"><?= e($p['reservation_code']) ?> &middot; <?= e($p['registration_no']) ?></div>
            </div>
            <span class="badge text-bg-primary fs-6"><?= e($p['pickup_time']) ?></span>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty"><i class="bi bi-calendar-x"></i>No pickups today.</div>
      <?php endif; ?>
    <?php card_close(); ?>

    <?php if ($canNotif): ?>
      <?php card_open('Latest alerts', '<span class="badge text-bg-danger me-2">' . (int)$unread . ' unread</span>', 'card-primary card-outline'); ?>
        <?php if ($latestAlerts): ?>
          <ul class="list-group list-group-flush">
          <?php foreach ($latestAlerts as $a): ?>
            <li class="list-group-item d-flex gap-3 align-items-start">
              <i class="bi <?= e(alert_icon($a['notification_type'])) ?> fs-5"></i>
              <div>
                <div class="fw-semibold"><?= e($a['subject']) ?></div>
                <div class="small text-muted"><?= e($a['created_at']) ?></div>
              </div>
            </li>
          <?php endforeach; ?>
          </ul>
          <div class="card-footer text-center">
            <a href="<?= e($root) ?>/notifications/list.php">See all notifications</a>
          </div>
        <?php else: ?>
          <div class="empty"><i class="bi bi-bell-slash"></i>No unread alerts.</div>
        <?php endif; ?>
      <?php card_close(); ?>
    <?php endif; ?>
  </div>
</div>

<?php
ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const fleetEl = document.getElementById('fleetChart');
  if (fleetEl) {
    new Chart(fleetEl, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($fleetLabels, JSON_HEX_TAG) ?>,
        datasets: [{ data: <?= json_encode($fleetData) ?>, backgroundColor: <?= json_encode($fleetColourList) ?>,
                     borderColor: '#0d6efd', borderWidth: 3 }]
      },
      options: { maintainAspectRatio: false, cutout: '62%',
                 plugins: { legend: { position: 'bottom', labels: { color: '#ffffff', padding: 16 } } } }
    });
  }
  const revEl = document.getElementById('revenueChart');
  if (revEl) {
    const ctx = revEl.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(13,110,253,.35)');
    grad.addColorStop(1, 'rgba(13,110,253,0)');
    new Chart(revEl, {
      type: 'line',
      data: {
        labels: <?= json_encode($revLabels, JSON_HEX_TAG) ?>,
        datasets: [{ label: 'Revenue (RWF)', data: <?= json_encode($revData) ?>, borderColor: '#0d6efd',
                     backgroundColor: grad, fill: true, tension: .4, pointRadius: 4, pointBackgroundColor: '#0d6efd' }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } } }
      }
    });
  }
</script>
<?php
layout_foot(ob_get_clean());
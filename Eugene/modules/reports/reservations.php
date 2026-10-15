<?php
// modules/reports/reservations.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_report_permission('reports.operational');

[$from, $to, $warn] = report_range();
[$start, $end] = range_bounds($from, $to);

// Summary by status
$st = $pdo->prepare(
    "SELECT status, COUNT(*) AS total, COALESCE(SUM(estimated_amount), 0) AS amount
     FROM reservations
     WHERE pickup_datetime >= ? AND pickup_datetime < ?
     GROUP BY status
     ORDER BY total DESC"
);
$st->execute([$start, $end]);
$byStatus = $st->fetchAll();

// Detail by pickup date and status
$st = $pdo->prepare(
    "SELECT DATE(pickup_datetime) AS day, status, COUNT(*) AS total, COALESCE(SUM(estimated_amount), 0) AS amount
     FROM reservations
     WHERE pickup_datetime >= ? AND pickup_datetime < ?
     GROUP BY DATE(pickup_datetime), status
     ORDER BY day, status"
);
$st->execute([$start, $end]);
$detail = $st->fetchAll();

if (wants_csv()) {
    csv_download("reservations_{$from}_to_{$to}.csv",
        ['Pickup date', 'Status', 'Reservations', 'Estimated amount (RWF)'], $detail);
}

report_header('Reservations report', 'reservations');
report_filter_form($from, $to, $warn);
?>
  <p class="text-muted small">Reservations are counted by their pickup date.</p>

  <h2 class="h5">By status</h2>
  <?php
  render_table(
      ['Status', 'Reservations', 'Estimated amount (RWF)'],
      array_map(fn($r) => [$r['status'], (int)$r['total'], $r['amount']], $byStatus),
      [2], [1],
      ['Total', array_sum(array_column($byStatus, 'total')), array_sum(array_column($byStatus, 'amount'))]
  );
  ?>

  <h2 class="h5">By date</h2>
  <?php
  render_table(
      ['Pickup date', 'Status', 'Reservations', 'Estimated amount (RWF)'],
      array_map(fn($r) => [$r['day'], $r['status'], (int)$r['total'], $r['amount']], $detail),
      [3], [2]
  );
  report_footer();
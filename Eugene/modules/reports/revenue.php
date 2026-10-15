<?php
// modules/reports/revenue.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_report_permission('reports.financial');

[$from, $to, $warn] = report_range();
[$start, $end] = range_bounds($from, $to);

// Whitelisted: the format string below never comes straight from the user.
$group = (($_GET['group'] ?? 'month') === 'day') ? 'day' : 'month';
$fmt   = ($group === 'day') ? '%Y-%m-%d' : '%Y-%m';

$st = $pdo->prepare(
    "SELECT DATE_FORMAT(payment_date, '$fmt') AS period, COUNT(*) AS payments, SUM(amount) AS revenue
     FROM payments
     WHERE status = 'completed' AND payment_date >= ? AND payment_date < ?
     GROUP BY period
     ORDER BY period"
);
$st->execute([$start, $end]);
$rows = $st->fetchAll();

$st = $pdo->prepare(
    "SELECT payment_method, COUNT(*) AS payments, SUM(amount) AS revenue
     FROM payments
     WHERE status = 'completed' AND payment_date >= ? AND payment_date < ?
     GROUP BY payment_method
     ORDER BY revenue DESC"
);
$st->execute([$start, $end]);
$byMethod = $st->fetchAll();

if (wants_csv()) {
    csv_download("revenue_{$group}_{$from}_to_{$to}.csv",
        [$group === 'day' ? 'Day' : 'Month', 'Payments', 'Revenue (RWF)'], $rows);
}

$extra = '<div class="col-auto"><label class="form-label mb-0 small">Group by</label>'
       . '<select name="group" class="form-select">'
       . '<option value="month"' . ($group === 'month' ? ' selected' : '') . '>Month</option>'
       . '<option value="day"'   . ($group === 'day'   ? ' selected' : '') . '>Day</option>'
       . '</select></div>';

report_header('Revenue report', 'revenue');
report_filter_form($from, $to, $warn, $extra);
?>
  <p class="text-muted small">Revenue = payments with status "completed", by payment date.</p>

  <h2 class="h5">By <?= e($group) ?></h2>
  <?php
  render_table(
      [$group === 'day' ? 'Day' : 'Month', 'Payments', 'Revenue (RWF)'],
      array_map(fn($r) => [$r['period'], (int)$r['payments'], $r['revenue']], $rows),
      [2], [1],
      ['Total', array_sum(array_column($rows, 'payments')), array_sum(array_column($rows, 'revenue'))]
  );
  ?>

  <h2 class="h5">By payment method</h2>
  <?php
  render_table(
      ['Method', 'Payments', 'Revenue (RWF)'],
      array_map(fn($r) => [str_replace('_', ' ', $r['payment_method']), (int)$r['payments'], $r['revenue']], $byMethod),
      [2], [1]
  );
  report_footer();
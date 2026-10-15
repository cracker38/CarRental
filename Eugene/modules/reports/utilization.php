<?php
// modules/reports/utilization.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_report_permission('reports.operational');

[$from, $to, $warn] = report_range();
[$start, $end] = range_bounds($from, $to);

$rangeDays = max(1, (strtotime($end) - strtotime($start)) / 86400);

// Days rented = time each rental overlaps the selected period (ongoing rentals count until now).
$st = $pdo->prepare(
    "SELECT v.registration_no,
            CONCAT(v.make, ' ', v.model) AS vehicle,
            COUNT(r.rental_id) AS rentals,
            COALESCE(SUM(GREATEST(0, TIMESTAMPDIFF(MINUTE,
                GREATEST(r.actual_pickup_datetime, CAST(? AS DATETIME)),
                LEAST(COALESCE(r.actual_return_datetime, NOW()), CAST(? AS DATETIME))
            ))), 0) / 1440 AS days_rented
     FROM vehicles v
     LEFT JOIN rentals r
            ON r.vehicle_id = v.vehicle_id
           AND r.actual_pickup_datetime IS NOT NULL
           AND r.status <> 'cancelled'
           AND r.actual_pickup_datetime < CAST(? AS DATETIME)
           AND COALESCE(r.actual_return_datetime, NOW()) >= CAST(? AS DATETIME)
     WHERE v.status <> 'inactive'
     GROUP BY v.vehicle_id, v.registration_no, v.make, v.model
     ORDER BY days_rented DESC, v.registration_no"
);
$st->execute([$start, $end, $end, $start]);
$rows = $st->fetchAll();

$data = [];
foreach ($rows as $r) {
    $days = round((float)$r['days_rented'], 1);
    $pct  = min(100, round($days / $rangeDays * 100, 1));
    $data[] = [$r['registration_no'], $r['vehicle'], (int)$r['rentals'], $days, $pct . '%'];
}

$headers = ['Registration', 'Vehicle', 'Rentals', 'Days rented', 'Utilization'];

if (wants_csv()) {
    csv_download("utilization_{$from}_to_{$to}.csv", $headers, $data);
}

report_header('Vehicle utilization report', 'utilization');
report_filter_form($from, $to, $warn);
?>
  <p class="text-muted small">
    Period length: <?= e(round($rangeDays)) ?> day(s). Utilization = days rented / days in the period.
    Rentals are counted if they overlap the period.
  </p>
  <?php
  render_table($headers, $data, [], [2, 3, 4],
      ['Total', null, array_sum(array_column($data, 2)), round(array_sum(array_column($data, 3)), 1), null]);
  report_footer();
<?php
// modules/reports/maintenance.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_report_permission('reports.operational');

[$from, $to, $warn] = report_range();

$st = $pdo->prepare(
    "SELECT v.registration_no,
            CONCAT(v.make, ' ', v.model) AS vehicle,
            COUNT(*) AS jobs,
            SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) AS completed_jobs,
            COALESCE(SUM(m.cost), 0) AS total_cost,
            COALESCE(AVG(m.cost), 0) AS avg_cost
     FROM maintenance_records m
     JOIN vehicles v ON v.vehicle_id = m.vehicle_id
     WHERE m.status <> 'cancelled'
       AND m.start_date BETWEEN ? AND ?
     GROUP BY v.vehicle_id, v.registration_no, v.make, v.model
     ORDER BY total_cost DESC, v.registration_no"
);
$st->execute([$from, $to]);
$rows = $st->fetchAll();

$data = array_map(fn($r) => [
    $r['registration_no'], $r['vehicle'], (int)$r['jobs'], (int)$r['completed_jobs'], $r['total_cost'], $r['avg_cost'],
], $rows);

$headers = ['Registration', 'Vehicle', 'Jobs', 'Completed', 'Total cost (RWF)', 'Average cost (RWF)'];

if (wants_csv()) {
    csv_download("maintenance_{$from}_to_{$to}.csv", $headers, $data);
}

report_header('Maintenance report', 'maintenance');
report_filter_form($from, $to, $warn);
?>
  <p class="text-muted small">Maintenance jobs that started in the period (cancelled jobs excluded).</p>
  <?php
  render_table($headers, $data, [4, 5], [2, 3],
      ['Total', null, array_sum(array_column($data, 2)), array_sum(array_column($data, 3)), array_sum(array_column($data, 4)), null]);
  report_footer();
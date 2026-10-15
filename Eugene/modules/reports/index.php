<?php
// modules/reports/index.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';

if (!has_permission('reports.operational') && !has_permission('reports.financial')) {
    http_response_code(403);
    exit('Access denied.');
}

$reports = [
    ['Reservations', 'reservations.php', 'reports.operational', 'Reservations by date and status.'],
    ['Revenue',      'revenue.php',      'reports.financial',   'Completed payments by day or month.'],
    ['Payments',     'payments.php',     'reports.financial',   'Invoices: paid versus outstanding.'],
    ['Vehicle utilization', 'utilization.php', 'reports.operational', 'Rentals and days rented per vehicle.'],
    ['Maintenance',  'maintenance.php',  'reports.operational', 'Maintenance jobs and cost per vehicle.'],
];

page_header('Reports');
?>
  <h1 class="h3 mb-3">Reports</h1>
  <div class="row g-3">
    <?php foreach ($reports as [$title, $href, $perm, $desc]): if (!has_permission($perm)) continue; ?>
      <div class="col-md-6 col-lg-4">
        <a href="<?= e($href) ?>" class="card h-100 text-decoration-none">
          <div class="card-body">
            <h2 class="h5"><?= e($title) ?></h2>
            <p class="text-muted mb-0"><?= e($desc) ?></p>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php page_footer(); ?>
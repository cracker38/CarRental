<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireLogin();
require_once BASE_PATH . '/includes/rental-operations-common.php';
ro_require_permission('inspections.manage');

$pdo = Database::connection();
$rentals = $pdo->query(
    'SELECT r.rental_id, r.rental_number, r.status, c.first_name, c.last_name,
            v.make, v.model, v.registration_no
     FROM rentals r
     JOIN customers c ON c.customer_id = r.customer_id
     JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     WHERE r.status <> \'cancelled\'
     ORDER BY r.updated_at DESC, r.rental_id DESC
     LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query(
    'SELECT vi.inspection_id, vi.inspection_type, vi.inspected_at, vi.odometer_reading,
            vi.fuel_level_percent, vi.exterior_condition, vi.interior_condition, vi.cleanliness,
            vi.damage_notes, r.rental_id, r.rental_number, c.first_name, c.last_name,
            v.make, v.model, v.registration_no, u.first_name AS staff_first, u.last_name AS staff_last,
            (SELECT COUNT(*) FROM inspection_images ii WHERE ii.inspection_id = vi.inspection_id) AS image_count
     FROM vehicle_inspections vi
     JOIN rentals r ON r.rental_id = vi.rental_id
     JOIN customers c ON c.customer_id = r.customer_id
     JOIN vehicles v ON v.vehicle_id = vi.vehicle_id
     LEFT JOIN users u ON u.user_id = vi.inspected_by
     ORDER BY vi.inspected_at DESC, vi.inspection_id DESC
     LIMIT 250'
);
$inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Vehicle Inspections';
$currentPage = 'inspections';
require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/rental-operations-nav.php';
?>
<div class="ro-shell">
    <section class="ro-page-heading">
        <div>
            <span class="ro-kicker">CONDITION &amp; SERVICE RECORDS</span>
            <h1>Vehicle inspections</h1>
            <p>Review pickup, return and follow-up inspections, including condition notes and attached evidence.</p>
        </div>
        <div class="ro-heading-symbol"><i class="bi bi-clipboard2-check"></i></div>
    </section>

    <section class="ro-panel mb-4">
        <div class="ro-panel-head">
            <div><span class="ro-kicker">FOLLOW-UP WORK</span><h2>Start an inspection</h2></div>
            <span class="ro-counter"><?= count($rentals) ?> recent rentals</span>
        </div>
        <?php if (!$rentals): ?>
            <div class="ro-empty compact"><strong>No rentals are available</strong><p>Rental records will appear here when agreements are signed and opened.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table ro-table align-middle">
                    <thead><tr><th>Rental</th><th>Customer</th><th>Vehicle</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($rentals as $rental): ?>
                            <tr>
                                <td><strong><?= e($rental['rental_number']) ?></strong></td>
                                <td><?= e($rental['first_name'] . ' ' . $rental['last_name']) ?></td>
                                <td><?= e($rental['make'] . ' ' . $rental['model'] . ' · ' . $rental['registration_no']) ?></td>
                                <td><?= ro_status_badge($rental['status']) ?></td>
                                <td><a class="btn btn-sm ro-btn-outline" href="<?= e(base_url('admin/inspection-form.php?rental_id=' . (int)$rental['rental_id'])) ?>"><i class="bi bi-plus-lg me-1"></i> New inspection</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="ro-panel">
        <div class="ro-panel-head">
            <div><span class="ro-kicker">INSPECTION REGISTER</span><h2>Latest records</h2></div>
            <span class="ro-counter"><?= count($inspections) ?> records</span>
        </div>
        <?php if (!$inspections): ?>
            <div class="ro-empty">
                <div class="ro-empty-icon"><i class="bi bi-clipboard2-check"></i></div>
                <strong>No inspections recorded</strong>
                <p>Pickup and return records will appear here after the rental workflow is completed.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table ro-table align-middle">
                    <thead>
                        <tr>
                            <th>Inspection</th>
                            <th>Rental / vehicle</th>
                            <th>Customer</th>
                            <th>Recorded</th>
                            <th>Readings</th>
                            <th>Condition</th>
                            <th>Evidence</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inspections as $inspection): ?>
                            <tr>
                                <td>
                                    <strong><?= e(ucwords(str_replace('_', ' ', $inspection['inspection_type']))) ?></strong>
                                    <small class="d-block text-secondary">#<?= (int)$inspection['inspection_id'] ?></small>
                                </td>
                                <td>
                                    <strong><?= e($inspection['rental_number']) ?></strong>
                                    <small class="d-block text-secondary"><?= e($inspection['make'] . ' ' . $inspection['model'] . ' · ' . $inspection['registration_no']) ?></small>
                                </td>
                                <td><?= e($inspection['first_name'] . ' ' . $inspection['last_name']) ?></td>
                                <td><?= e(ro_date($inspection['inspected_at'])) ?><small class="d-block text-secondary"><?= e(trim(($inspection['staff_first'] ?? '') . ' ' . ($inspection['staff_last'] ?? '')) ?: 'System') ?></small></td>
                                <td>
                                    <?= $inspection['odometer_reading'] === null ? '—' : number_format((int)$inspection['odometer_reading']) . ' km' ?>
                                    <small class="d-block text-secondary"><?= $inspection['fuel_level_percent'] === null ? 'Fuel —' : number_format((float)$inspection['fuel_level_percent'], 1) . '% fuel' ?></small>
                                </td>
                                <td>
                                    <?= e(ucfirst((string)($inspection['exterior_condition'] ?? 'Not recorded'))) ?> exterior
                                    <small class="d-block text-secondary"><?= e(ucfirst((string)($inspection['interior_condition'] ?? 'Not recorded'))) ?> interior · <?= e(ucwords(str_replace('_', ' ', (string)($inspection['cleanliness'] ?? 'not recorded')))) ?></small>
                                    <?php if (!empty($inspection['damage_notes'])): ?><small class="d-block text-danger"><?= e($inspection['damage_notes']) ?></small><?php endif; ?>
                                </td>
                                <td><?= (int)$inspection['image_count'] ?></td>
                                <td>
                                    <a class="btn btn-sm ro-btn-outline" href="<?= e(base_url('admin/inspection-form.php?inspection_id=' . (int)$inspection['inspection_id'])) ?>">
                                        Review <i class="bi bi-arrow-up-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

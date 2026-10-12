<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireLogin();
require_once BASE_PATH . '/includes/rental-operations-common.php';
ro_require_permission('rental.view');

$pageTitle = 'Rental Operations';
$currentPage = 'dashboard';
$pdo = Database::connection();
RentalOperations::refreshOverdueRentals($pdo);

$stats = [
    'agreements' => (int)$pdo->query("SELECT COUNT(*) FROM rental_agreements WHERE status NOT IN ('terminated')")->fetchColumn(),
    'active_rentals' => (int)$pdo->query("SELECT COUNT(*) FROM rentals WHERE status IN ('active','overdue')")->fetchColumn(),
    'overdue_rentals' => (int)$pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'overdue'")->fetchColumn(),
    'open_invoices' => (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('issued','partially_paid','overdue')")->fetchColumn(),
    'outstanding' => (float)$pdo->query("SELECT COALESCE(SUM(outstanding_balance),0) FROM vw_invoice_balances WHERE status NOT IN ('void','paid','refunded')")->fetchColumn(),
    'pending_reservations' => (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'confirmed' AND reservation_id NOT IN (SELECT reservation_id FROM rental_agreements)")->fetchColumn(),
    'unbilled' => (int)$pdo->query("SELECT COUNT(*) FROM rentals r LEFT JOIN invoices i ON i.rental_id = r.rental_id AND i.status <> 'void' WHERE r.status IN ('returned','inspected') AND i.invoice_id IS NULL")->fetchColumn(),
    'inspection_followups' => (int)$pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'inspected'")->fetchColumn(),
    'refunds' => (int)$pdo->query("SELECT COUNT(*) FROM payment_refunds WHERE status IN ('requested','approved')")->fetchColumn(),
];

$recent = $pdo->query(
    "SELECT r.rental_id, r.rental_number, r.status, r.expected_return_datetime, c.first_name, c.last_name, v.make, v.model, v.registration_no
     FROM rentals r
     JOIN customers c ON c.customer_id = r.customer_id
     JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     ORDER BY FIELD(r.status,'overdue','active','scheduled','inspected','returned','closed','cancelled'), r.expected_return_datetime ASC
     LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

$due = $pdo->query(
    "SELECT i.invoice_id, i.invoice_number, i.due_date, i.total_amount, b.outstanding_balance, c.first_name, c.last_name
     FROM invoices i
     JOIN customers c ON c.customer_id = i.customer_id
     JOIN vw_invoice_balances b ON b.invoice_id = i.invoice_id
     WHERE i.status IN ('issued','partially_paid','overdue')
     ORDER BY i.due_date IS NULL, i.due_date ASC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

require BASE_PATH . '/includes/header.php';
require BASE_PATH . '/includes/rental-operations-nav.php';
?>
<div class="ro-shell">
    <section class="ro-hero">
        <div class="ro-hero-copy">
            <div class="ro-eyebrow"><i class="bi bi-activity"></i> OPERATIONS CONTROL CENTRE</div>
            <h1>Keep every rental moving.</h1>
            <p>Agreements, vehicle handovers, inspections, invoices, payments and deposits — together in one workspace.</p>
            <div class="ro-hero-meta">
                <span><i class="bi bi-person-circle"></i> <?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></span>
                <span><i class="bi bi-calendar3"></i> <?= e(date('l, d F Y')) ?></span>
            </div>
        </div>
        <div class="ro-hero-art">
            <div class="ro-art-circle"></div>
            <i class="bi bi-car-front-fill"></i>
            <span class="ro-art-tag tag-one"><i class="bi bi-shield-check"></i> Agreements</span>
            <span class="ro-art-tag tag-two"><i class="bi bi-receipt"></i> Billing</span>
        </div>
    </section>

    <div class="ro-section-heading">
        <div><span class="ro-kicker">AT A GLANCE</span><h2>Today's portfolio</h2></div>
        <span class="ro-live"><span></span> Live database counts</span>
    </div>

    <div class="ro-kpi-grid">
        <a class="ro-kpi kpi-violet" href="<?= e(base_url('admin/agreements.php')) ?>">
            <span class="ro-kpi-icon"><i class="bi bi-file-earmark-check"></i></span>
            <span class="ro-kpi-label">Agreements</span>
            <strong><?= number_format($stats['agreements']) ?></strong>
            <small><?= (int)$stats['pending_reservations'] ?> reservation(s) waiting</small>
        </a>
        <a class="ro-kpi kpi-blue" href="<?= e(base_url('admin/rentals.php')) ?>">
            <span class="ro-kpi-icon"><i class="bi bi-car-front"></i></span>
            <span class="ro-kpi-label">Active rentals</span>
            <strong><?= number_format($stats['active_rentals']) ?></strong>
            <small><?= (int)$stats['overdue_rentals'] ?> overdue</small>
        </a>
        <a class="ro-kpi kpi-amber" href="<?= e(base_url('admin/invoices.php')) ?>">
            <span class="ro-kpi-icon"><i class="bi bi-receipt-cutoff"></i></span>
            <span class="ro-kpi-label">Open invoices</span>
            <strong><?= number_format($stats['open_invoices']) ?></strong>
            <small><?= (int)$stats['unbilled'] ?> rental(s) unbilled</small>
        </a>
        <a class="ro-kpi kpi-green" href="<?= e(base_url('admin/invoices.php')) ?>">
            <span class="ro-kpi-icon"><i class="bi bi-wallet2"></i></span>
            <span class="ro-kpi-label">Outstanding balance</span>
            <strong class="ro-kpi-money"><?= ro_money($stats['outstanding']) ?></strong>
            <small>Net of processed refunds</small>
        </a>
    </div>

    <div class="ro-content-grid">
        <section class="ro-panel ro-panel-main">
            <div class="ro-panel-head">
                <div><span class="ro-kicker">WORK QUEUE</span><h2>Priority actions</h2></div>
                <span class="ro-panel-icon"><i class="bi bi-lightning-charge"></i></span>
            </div>
            <div class="ro-action-list">
                <a href="<?= e(base_url('admin/agreements.php')) ?>" class="ro-action-row">
                    <span class="ro-action-icon action-purple"><i class="bi bi-file-earmark-plus"></i></span>
                    <span class="ro-action-copy"><strong>Generate rental agreements</strong><small>Confirmed reservations ready for paperwork</small></span>
                    <span class="ro-action-count"><?= (int)$stats['pending_reservations'] ?></span>
                    <i class="bi bi-chevron-right ro-chevron"></i>
                </a>
                <a href="<?= e(base_url('admin/rentals.php')) ?>" class="ro-action-row">
                    <span class="ro-action-icon action-blue"><i class="bi bi-key"></i></span>
                    <span class="ro-action-copy"><strong>Pickup and return desk</strong><small>Capture mileage, fuel and vehicle condition</small></span>
                    <?php if ($stats['overdue_rentals']): ?><span class="ro-action-count count-alert"><?= (int)$stats['overdue_rentals'] ?></span><?php endif; ?>
                    <i class="bi bi-chevron-right ro-chevron"></i>
                </a>
                <a href="<?= e(base_url('admin/invoices.php')) ?>" class="ro-action-row">
                    <span class="ro-action-icon action-orange"><i class="bi bi-receipt"></i></span>
                    <span class="ro-action-copy"><strong>Unbilled returned rentals</strong><small>Generate itemized invoices from final charges</small></span>
                    <span class="ro-action-count <?= $stats['unbilled'] ? 'count-alert' : '' ?>"><?= (int)$stats['unbilled'] ?></span>
                    <i class="bi bi-chevron-right ro-chevron"></i>
                </a>
                <a href="<?= e(base_url('admin/refunds.php')) ?>" class="ro-action-row">
                    <span class="ro-action-icon action-orange"><i class="bi bi-arrow-counterclockwise"></i></span>
                    <span class="ro-action-copy"><strong>Refund approvals</strong><small>Review requests before processing</small></span>
                    <span class="ro-action-count <?= $stats['refunds'] ? 'count-alert' : '' ?>"><?= (int)$stats['refunds'] ?></span>
                    <i class="bi bi-chevron-right ro-chevron"></i>
                </a>
                <a href="<?= e(base_url('admin/deposits.php')) ?>" class="ro-action-row">
                    <span class="ro-action-icon action-green"><i class="bi bi-safe2"></i></span>
                    <span class="ro-action-copy"><strong>Security deposits</strong><small>Record receipts, deductions and returns</small></span>
                    <?php if ($stats['inspection_followups']): ?><span class="ro-action-count"><?= (int)$stats['inspection_followups'] ?></span><?php endif; ?>
                    <i class="bi bi-chevron-right ro-chevron"></i>
                </a>
            </div>
        </section>

        <section class="ro-panel">
            <div class="ro-panel-head">
                <div><span class="ro-kicker">FLEET ACTIVITY</span><h2>Priority rentals</h2></div>
                <a class="ro-text-link" href="<?= e(base_url('admin/rentals.php')) ?>">View all <i class="bi bi-arrow-up-right"></i></a>
            </div>
            <?php if (!$recent): ?>
                <div class="ro-empty compact">
                    <div class="ro-empty-icon"><i class="bi bi-car-front"></i></div>
                    <strong>No rentals yet</strong>
                    <p>Signed agreements can be converted into rentals.</p>
                </div>
            <?php else: ?>
                <div class="ro-record-list">
                    <?php foreach ($recent as $row): ?>
                        <a class="ro-record" href="<?= e(base_url('admin/rental-view.php?id=' . (int)$row['rental_id'])) ?>">
                            <span class="ro-record-icon"><i class="bi bi-car-front-fill"></i></span>
                            <span class="ro-record-copy">
                                <strong><?= e($row['rental_number']) ?></strong>
                                <small><?= e($row['make'] . ' ' . $row['model'] . ' · ' . $row['registration_no']) ?></small>
                                <small><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></small>
                            </span>
                            <span class="ro-record-end">
                                <?= ro_status_badge($row['status']) ?>
                                <small>Due <?= e(ro_date($row['expected_return_datetime'])) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="ro-panel ro-panel-wide">
            <div class="ro-panel-head">
                <div><span class="ro-kicker">RECEIVABLES</span><h2>Invoices to follow up</h2></div>
                <a class="ro-text-link" href="<?= e(base_url('admin/invoices.php')) ?>">All invoices <i class="bi bi-arrow-up-right"></i></a>
            </div>
            <?php if (!$due): ?>
                <div class="ro-empty compact">
                    <div class="ro-empty-icon"><i class="bi bi-check2-circle"></i></div>
                    <strong>Nothing waiting for payment</strong>
                    <p>There are no open invoices to show.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ro-table align-middle">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Due date</th>
                                <th>Total</th>
                                <th>Outstanding</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($due as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['invoice_number']) ?></strong></td>
                                    <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><?= e(ro_date($row['due_date'])) ?></td>
                                    <td><?= ro_money($row['total_amount']) ?></td>
                                    <td><strong><?= ro_money($row['outstanding_balance']) ?></strong></td>
                                    <td><a class="btn btn-sm ro-btn-outline" href="<?= e(base_url('admin/invoice-view.php?id=' . (int)$row['invoice_id'])) ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <p class="ro-footnote">
        <i class="bi bi-shield-check"></i>
        Financial changes are recorded in the database. Card and mobile-money entries are not payment-gateway transactions; reconcile them with the provider before marking money received.
    </p>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>

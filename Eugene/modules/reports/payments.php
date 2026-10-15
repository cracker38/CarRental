<?php
// modules/reports/payments.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_report_permission('reports.financial');

[$from, $to, $warn] = report_range();

$st = $pdo->prepare(
    "SELECT b.invoice_number,
            CONCAT(c.first_name, ' ', c.last_name) AS customer,
            b.issue_date, b.due_date, b.status,
            b.total_amount,
            (b.total_paid_gross - b.total_refunded) AS net_paid,
            b.outstanding_balance
     FROM vw_invoice_balances b
     JOIN customers c ON c.customer_id = b.customer_id
     WHERE b.issue_date BETWEEN ? AND ?
       AND b.status NOT IN ('void', 'draft')
     ORDER BY b.issue_date DESC, b.invoice_number"
);
$st->execute([$from, $to]);
$rows = $st->fetchAll();

$data = array_map(fn($r) => [
    $r['invoice_number'], $r['customer'], $r['issue_date'], $r['due_date'],
    str_replace('_', ' ', $r['status']), $r['total_amount'], $r['net_paid'], $r['outstanding_balance'],
], $rows);

$headers = ['Invoice', 'Customer', 'Issued', 'Due', 'Status', 'Invoiced (RWF)', 'Paid (RWF)', 'Outstanding (RWF)'];

if (wants_csv()) {
    csv_download("payments_{$from}_to_{$to}.csv", $headers, $data);
}

$totInvoiced    = array_sum(array_column($rows, 'total_amount'));
$totPaid        = array_sum(array_column($rows, 'net_paid'));
$totOutstanding = array_sum(array_column($rows, 'outstanding_balance'));

report_header('Payments report', 'payments');
report_filter_form($from, $to, $warn);
?>
  <p class="text-muted small">
    Invoices issued in the period (draft and void excluded). Paid = payments received minus processed refunds.
  </p>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card text-bg-primary"><div class="card-body">
      <div class="small text-uppercase">Invoiced</div><div class="fs-4 fw-bold"><?= e(money($totInvoiced)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card text-bg-success"><div class="card-body">
      <div class="small text-uppercase">Paid</div><div class="fs-4 fw-bold"><?= e(money($totPaid)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card text-bg-danger"><div class="card-body">
      <div class="small text-uppercase">Outstanding</div><div class="fs-4 fw-bold"><?= e(money($totOutstanding)) ?></div></div></div></div>
  </div>

  <?php
  render_table($headers, $data, [5, 6, 7], [],
      ['Total', null, null, null, null, $totInvoiced, $totPaid, $totOutstanding]);
  report_footer();
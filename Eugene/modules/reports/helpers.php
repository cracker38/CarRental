<?php
// modules/reports/helpers.php  (Member 5: Eugenie)
// Reuses $pdo, session, e(), page_header(), page_footer() from the maintenance helpers.
// Replace with Elias's shared includes when they exist.
require_once __DIR__ . '/../maintenance/helpers.php';

function require_report_permission(string $key): void {
    if (!has_permission($key)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function valid_date($v): bool {
    if (!is_string($v) || $v === '') return false;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

/** Reads ?from=&to= (default: first day of this month to today). Returns [from, to, warning|null]. */
function report_range(): array {
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to'] ?? '';
    $warn = null;
    if (!valid_date($from)) $from = date('Y-m-01');
    if (!valid_date($to))   $to   = date('Y-m-d');
    if ($from > $to) {
        [$from, $to] = [$to, $from];
        $warn = 'The "from" date was after the "to" date, so they were swapped.';
    }
    return [$from, $to, $warn];
}

/** [start 00:00:00, day-after-end 00:00:00] for use with  col >= ? AND col < ? */
function range_bounds(string $from, string $to): array {
    $end = (new DateTime($to))->modify('+1 day')->format('Y-m-d');
    return [$from . ' 00:00:00', $end . ' 00:00:00'];
}

function money($v): string {
    return number_format((float)$v, 2);
}

// --- CSV export --------------------------------------------------------------
function csv_cell($v) {
    // Stop spreadsheet formula injection from text such as "=1+1" typed into names.
    if (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) {
        return "'" . $v;
    }
    return $v;
}

function csv_download(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel reads accents correctly
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_cell', array_values($r)), ',', '"', '');
    }
    fclose($out);
    exit;
}

function wants_csv(): bool {
    return ($_GET['export'] ?? '') === 'csv';
}

// --- Layout --------------------------------------------------------------------
function report_header(string $title, string $active): void {
    page_header($title);
    $links = [
        'reservations' => ['Reservations', 'reservations.php', 'reports.operational'],
        'revenue'      => ['Revenue',      'revenue.php',      'reports.financial'],
        'payments'     => ['Payments',     'payments.php',     'reports.financial'],
        'utilization'  => ['Utilization',  'utilization.php',  'reports.operational'],
        'maintenance'  => ['Maintenance',  'maintenance.php',  'reports.operational'],
    ];
    echo '<ul class="nav nav-pills mb-3 d-print-none">';
    echo '<li class="nav-item"><a class="nav-link" href="index.php">All reports</a></li>';
    foreach ($links as $key => [$label, $href, $perm]) {
        if (!has_permission($perm)) continue;
        echo '<li class="nav-item"><a class="nav-link ' . ($active === $key ? 'active' : '') . '" href="' . e($href) . '">' . e($label) . '</a></li>';
    }
    echo '</ul>';
    echo '<h1 class="h3 mb-3">' . e($title) . '</h1>';
}

/** Date filter + Print + Export CSV buttons. $extraHtml is trusted HTML (already escaped). */
function report_filter_form(string $from, string $to, ?string $warn = null, string $extraHtml = ''): void {
    $csvQuery = http_build_query(array_merge($_GET, ['from' => $from, 'to' => $to, 'export' => 'csv']));
    if ($warn) {
        echo '<div class="alert alert-warning py-2 d-print-none">' . e($warn) . '</div>';
    }
    ?>
    <form method="get" class="row g-2 align-items-end mb-3 d-print-none">
      <div class="col-auto">
        <label class="form-label mb-0 small">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-0 small">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
      </div>
      <?= $extraHtml ?>
      <div class="col-auto"><button class="btn btn-primary">Apply</button></div>
      <div class="col-auto ms-auto d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>
        <a class="btn btn-outline-success" href="?<?= e($csvQuery) ?>">Export CSV</a>
      </div>
    </form>
    <p class="text-muted small mb-3">Period: <strong><?= e($from) ?></strong> to <strong><?= e($to) ?></strong></p>
    <?php
}

/**
 * Simple results table. $rows = list of arrays in header order.
 * $moneyCols = column indexes shown as money; $countCols = right-aligned integers.
 * $totals = optional last row (same length as headers; null = empty cell).
 */
function render_table(array $headers, array $rows, array $moneyCols = [], array $countCols = [], ?array $totals = null): void {
    $right = array_merge($moneyCols, $countCols);
    ?>
    <div class="card mb-4">
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0 align-middle">
          <thead>
            <tr>
              <?php foreach ($headers as $i => $h): ?>
                <th class="<?= in_array($i, $right, true) ? 'text-end' : '' ?>"><?= e($h) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="<?= count($headers) ?>" class="text-muted">No data for this period.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $r): $r = array_values($r); ?>
            <tr>
              <?php foreach ($r as $i => $cell): ?>
                <td class="<?= in_array($i, $right, true) ? 'text-end' : '' ?>">
                  <?= in_array($i, $moneyCols, true) ? e(money($cell)) : e($cell ?? '-') ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <?php if ($totals && $rows): ?>
          <tfoot>
            <tr class="fw-bold table-light">
              <?php foreach ($totals as $i => $cell): ?>
                <td class="<?= in_array($i, $right, true) ? 'text-end' : '' ?>">
                  <?= $cell === null ? '' : (in_array($i, $moneyCols, true) ? e(money($cell)) : e($cell)) ?>
                </td>
              <?php endforeach; ?>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
    <?php
}

function report_footer(): void {
    page_footer();
}
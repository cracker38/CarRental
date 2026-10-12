<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/RentalOperations.php';

function ro_require_permission(string $permission): void
{
    if (!function_exists('hasPermission') || !hasPermission($permission)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require BASE_PATH . '/includes/header.php';
        echo '<div class="container py-5"><div class="ro-empty"><div class="ro-empty-icon"><i class="bi bi-shield-lock"></i></div><h1 class="h4">Access restricted</h1><p>Your staff account does not have permission to open this module.</p></div></div>';
        require BASE_PATH . '/includes/footer.php';
        exit;
    }
}

function ro_styles(): void
{
    echo '<link rel="stylesheet" href="' . e(base_url('assets/rental-operations.css')) . '">';
}

function ro_nav(string $active): void
{
    $items = [
        ['dashboard','Operations','admin/rental-operations.php','bi-grid-1x2','rental.view'],
        ['agreements','Agreements','admin/agreements.php','bi-file-earmark-text','agreements.manage'],
        ['rentals','Rentals','admin/rentals.php','bi-car-front','rentals.manage'],
        ['invoices','Invoices','admin/invoices.php','bi-receipt-cutoff','invoices.manage'],
        ['payments','Payments','admin/payments.php','bi-credit-card','payments.manage'],
        ['refunds','Refunds','admin/refunds.php','bi-arrow-counterclockwise','refunds.manage'],
        ['deposits','Deposits','admin/deposits.php','bi-safe2','deposits.manage'],
    ];
    echo '<nav class="ro-nav" aria-label="Rental operations">';
    foreach ($items as [$key,$label,$url,$icon,$permission]) {
        if (hasPermission($permission)) {
            echo '<a class="ro-nav-link' . ($active === $key ? ' active' : '') . '" href="' . e(base_url($url)) . '"><i class="bi ' . e($icon) . '"></i><span>' . e($label) . '</span></a>';
        }
    }
    echo '</nav>';
}

function ro_flash(string $type, string $message): void
{
    $_SESSION['_ro_flash'] = ['type' => $type, 'message' => $message];
}

function ro_render_flash(): void
{
    if (empty($_SESSION['_ro_flash']) || !is_array($_SESSION['_ro_flash'])) return;
    $flash = $_SESSION['_ro_flash']; unset($_SESSION['_ro_flash']);
    $type = in_array($flash['type'] ?? '', ['success','danger','warning','info'], true) ? $flash['type'] : 'info';
    echo '<div class="alert alert-' . $type . ' ro-alert"><i class="bi ' . ($type === 'success' ? 'bi-check-circle' : 'bi-info-circle') . '"></i><span>' . e((string)($flash['message'] ?? '')) . '</span></div>';
}

function ro_money(mixed $amount, ?string $currency = null): string
{
    $currency ??= function_exists('setting') ? (string)setting('default_currency', 'RWF') : 'RWF';
    return e($currency) . ' ' . number_format((float)$amount, 2);
}

function ro_date(mixed $value, string $fallback = '—'): string
{
    if (!$value) return $fallback;
    try { return (new DateTimeImmutable((string)$value))->format(str_contains((string)$value, ':') ? 'd M Y, H:i' : 'd M Y'); }
    catch (Throwable) { return $fallback; }
}

function ro_status_badge(string $status): string
{
    $known = [
        'draft', 'issued', 'signed', 'active', 'completed', 'terminated', 'scheduled', 'overdue',
        'returned', 'inspected', 'closed', 'cancelled', 'partially_paid', 'paid', 'void', 'refunded',
        'partially_refunded', 'pending', 'approved', 'processed', 'rejected', 'failed', 'requested',
        'received', 'deduction', 'refund', 'adjustment',
    ];
    $safe = in_array($status, $known, true) ? $status : 'pending';
    return '<span class="ro-status status-' . e($safe) . '">' . e(ucwords(str_replace('_', ' ', $safe))) . '</span>';
}

/** Suggested late-return fee from daily rate × overdue calendar days (staff can edit). */
function ro_suggest_late_fee(float $dailyRate, int $lateDays): float
{
    if ($dailyRate <= 0 || $lateDays <= 0) {
        return 0.0;
    }
    return round($dailyRate * $lateDays, 2);
}

function ro_post_guard(): void
{
    if (!is_post()) return;
    if (class_exists('Csrf')) Csrf::requireValid();
}

function ro_redirect(string $path): never
{
    redirect($path);
}

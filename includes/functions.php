<?php
/**
 * Shared helper functions
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): ?string
{
    if (!empty($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function formatMoney($amount): string
{
    return number_format((float)$amount, 0) . ' RWF';
}

function vehicleStatuses(): array
{
    return ['available', 'reserved', 'rented', 'maintenance', 'inactive'];
}

function statusBadge(string $status): string
{
    return '<span class="status status-' . e($status) . '">' . e($status) . '</span>';
}


/**
 * Vehicle image management: Business Manager & Rental Officer only.
 * System Administrator is intentionally excluded.
 */
function canManageVehicleImages(): bool
{
    // "Manager" in this system = Business Manager role
    // System Administrator is NOT allowed to edit vehicle images
    $role = $_SESSION['role_name'] ?? '';
    return $role === 'Business Manager' || $role === 'Rental Officer';
}

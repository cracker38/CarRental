<?php
// modules/maintenance/helpers.php  (Member 5: Eugenie)

require_once __DIR__ . '/../../config/db.php'; // replace with the shared config/db from Elias
require_once __DIR__ . '/../nav.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// --- CSRF (replace with Elias's version when it exists) --------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(400);
        exit('Invalid form token. Go back and try again.');
    }
}

// --- Permission ------------------------------------------------------------
function require_maintenance_permission(): void {
    if (!has_permission('maintenance.manage')) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// --- Maintenance numbers: MNT-2026-0001 ------------------------------------
// Call INSIDE a transaction so the lock prevents two users getting the same number.
function next_maintenance_number(PDO $pdo): string {
    $year   = date('Y');
    $prefix = "MNT-$year-";
    $st = $pdo->prepare(
        "SELECT maintenance_number FROM maintenance_records
         WHERE maintenance_number LIKE ? ORDER BY maintenance_number DESC LIMIT 1 FOR UPDATE"
    );
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $n = $last ? ((int)substr($last, -4)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

// --- Keep vehicles.status in sync with maintenance status -------------------
// Call INSIDE a transaction. Throws RuntimeException if the change is not allowed.
function sync_vehicle_status(PDO $pdo, int $vehicleId, string $maintStatus, int $maintenanceId = 0): void {
    if ($maintStatus === 'in_progress') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE vehicle_id = ? AND status IN ('active','overdue')");
        $st->execute([$vehicleId]);
        if ((int)$st->fetchColumn() > 0) {
            throw new RuntimeException('This vehicle has an active or overdue rental and cannot be sent to maintenance.');
        }
        $pdo->prepare("UPDATE vehicles SET status = 'maintenance' WHERE vehicle_id = ?")->execute([$vehicleId]);
    } elseif ($maintStatus === 'completed' || $maintStatus === 'cancelled') {
        // Only free the vehicle if no other job is still in progress.
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM maintenance_records
             WHERE vehicle_id = ? AND status = 'in_progress' AND maintenance_id <> ?"
        );
        $st->execute([$vehicleId, $maintenanceId]);
        if ((int)$st->fetchColumn() === 0) {
            $pdo->prepare("UPDATE vehicles SET status = 'available' WHERE vehicle_id = ? AND status = 'maintenance'")
                ->execute([$vehicleId]);
        }
    }
}

const MAINTENANCE_TYPES = [
    'routine_service' => 'Routine service',
    'repair'          => 'Repair',
    'inspection'      => 'Inspection',
    'tyres'           => 'Tyres',
    'oil_change'      => 'Oil change',
    'bodywork'        => 'Bodywork',
    'other'           => 'Other',
];

const MAINTENANCE_STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

function status_badge(string $s): string {
    $map = ['scheduled' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'dark'];
    return '<span class="badge text-bg-' . ($map[$s] ?? 'light') . '">' . e(str_replace('_', ' ', $s)) . '</span>';
}

// --- Page layout -----------------------------------------------------------
// The look (sidebar, top bar, styles) lives in modules/nav.php (AdminLTE 4 layout).
function page_header(string $title): void {
    layout_head($title);
}

function page_footer(): void {
    layout_foot();
}
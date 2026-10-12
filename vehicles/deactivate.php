<?php
/**
 * Change vehicle status with coordination rules.
 * - Setting inactive: always allowed (manual lock)
 * - Setting available: blocked if active rental / open maintenance
 * - Setting maintenance / rented / reserved: allowed, then sync may refine
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vehicle_status.php';
requirePermission('vehicles.manage');

$id     = (int)($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

if ($id <= 0 || !in_array($status, vehicleStatuses(), true)) {
    redirect('/vehicles/index.php');
}

$pdo = getPDO();

// Verify vehicle exists
$exists = $pdo->prepare('SELECT status FROM vehicles WHERE vehicle_id = ?');
$exists->execute([$id]);
$current = $exists->fetchColumn();
if ($current === false) {
    setFlash('error', 'Vehicle not found.');
    redirect('/vehicles/index.php');
}

if ($status === 'available') {
    $info = computeOperationalStatus($id, $pdo);
    if ($info['active_rentals'] > 0) {
        setFlash('error', 'Cannot set Available: vehicle has an active/overdue rental. Complete the return first.');
        redirect('/vehicles/details.php?id=' . $id);
    }
    if ($info['open_maintenance'] > 0) {
        setFlash('error', 'Cannot set Available: vehicle has open maintenance. Complete or cancel maintenance first.');
        redirect('/vehicles/details.php?id=' . $id);
    }
}

// Apply manual status
$pdo->prepare('UPDATE vehicles SET status = ? WHERE vehicle_id = ?')->execute([$status, $id]);

// If not locking as inactive, re-sync so reservation/maintenance still win when appropriate
if ($status !== 'inactive') {
    $final = syncVehicleStatus($id, $pdo);
    if ($final !== $status) {
        setFlash('success', "Status set to \"$status\", then adjusted to \"$final\" based on rentals/maintenance.");
        redirect('/vehicles/details.php?id=' . $id);
    }
}

setFlash('success', 'Vehicle status updated to "' . $status . '".');
redirect('/vehicles/details.php?id=' . $id);

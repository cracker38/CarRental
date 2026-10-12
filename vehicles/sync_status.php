<?php
/**
 * Manually re-sync one vehicle (or all) from rentals + maintenance + reservations.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vehicle_status.php';
requirePermission('vehicles.manage');

$id  = (int)($_GET['id'] ?? 0);
$all = isset($_GET['all']);

if ($all) {
    $n = syncAllVehicleStatuses();
    setFlash('success', "Synced status for $n vehicle(s).");
    redirect('/vehicles/index.php');
}

if ($id <= 0) {
    redirect('/vehicles/index.php');
}

$before = getPDO()->prepare('SELECT status FROM vehicles WHERE vehicle_id = ?');
$before->execute([$id]);
$old = $before->fetchColumn();

$new = syncVehicleStatus($id);

if ($old === $new) {
    setFlash('success', "Status already correct: \"$new\".");
} else {
    setFlash('success', "Status updated: \"$old\" → \"$new\".");
}
redirect('/vehicles/details.php?id=' . $id);

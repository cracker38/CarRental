<?php
/**
 * Vehicle status coordination with Reservations & Maintenance
 *
 * Rules (priority high → low):
 *  1. inactive          → never auto-changed (manual only)
 *  2. active/overdue rental → rented
 *  3. open maintenance  → maintenance
 *  4. confirmed reservation covering "now" → reserved
 *  5. otherwise         → available
 *
 * Call syncVehicleStatus($vehicleId) from:
 *  - Rental pickup / return / cancel
 *  - Maintenance start / complete / cancel
 *  - Reservation confirm / cancel
 *  - Manual "Sync status" on vehicle profile
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Compute what the vehicle status SHOULD be right now.
 * Does not write to DB.
 */
function computeOperationalStatus(int $vehicleId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? getPDO();

    $v = $pdo->prepare('SELECT status FROM vehicles WHERE vehicle_id = ?');
    $v->execute([$vehicleId]);
    $recorded = $v->fetchColumn();
    if ($recorded === false) {
        return ['status' => null, 'reason' => 'Vehicle not found', 'recorded' => null];
    }

    // Manual lock: inactive is only changed by staff
    if ($recorded === 'inactive') {
        return [
            'status'   => 'inactive',
            'reason'   => 'Manually set to inactive',
            'recorded' => $recorded,
            'active_rentals' => 0,
            'open_maintenance' => 0,
            'active_reservations' => 0,
        ];
    }

    // 1) Active or overdue rentals
    $r = $pdo->prepare("
        SELECT COUNT(*) FROM rentals
        WHERE vehicle_id = ? AND status IN ('active', 'overdue')
    ");
    $r->execute([$vehicleId]);
    $activeRentals = (int)$r->fetchColumn();
    if ($activeRentals > 0) {
        return [
            'status'   => 'rented',
            'reason'   => "Active/overdue rental(s): $activeRentals",
            'recorded' => $recorded,
            'active_rentals' => $activeRentals,
            'open_maintenance' => 0,
            'active_reservations' => 0,
        ];
    }

    // 2) Open maintenance
    $m = $pdo->prepare("
        SELECT COUNT(*) FROM maintenance_records
        WHERE vehicle_id = ? AND status IN ('scheduled', 'in_progress')
    ");
    $m->execute([$vehicleId]);
    $openMaint = (int)$m->fetchColumn();
    if ($openMaint > 0) {
        return [
            'status'   => 'maintenance',
            'reason'   => "Open maintenance record(s): $openMaint",
            'recorded' => $recorded,
            'active_rentals' => 0,
            'open_maintenance' => $openMaint,
            'active_reservations' => 0,
        ];
    }

    // 3) Confirmed reservation covering current time
    $res = $pdo->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE vehicle_id = ?
          AND status IN ('confirmed')
          AND pickup_datetime <= NOW()
          AND return_datetime >= NOW()
    ");
    $res->execute([$vehicleId]);
    $activeRes = (int)$res->fetchColumn();
    if ($activeRes > 0) {
        return [
            'status'   => 'reserved',
            'reason'   => "Confirmed reservation in progress: $activeRes",
            'recorded' => $recorded,
            'active_rentals' => 0,
            'open_maintenance' => 0,
            'active_reservations' => $activeRes,
        ];
    }

    // 4) Default
    return [
        'status'   => 'available',
        'reason'   => 'No active rental, maintenance, or current reservation',
        'recorded' => $recorded,
        'active_rentals' => 0,
        'open_maintenance' => 0,
        'active_reservations' => 0,
    ];
}

/**
 * Write computed status to vehicles.status (unless inactive).
 * Returns the new status string.
 */
function syncVehicleStatus(int $vehicleId, ?PDO $pdo = null): string
{
    $pdo = $pdo ?? getPDO();
    $info = computeOperationalStatus($vehicleId, $pdo);

    if ($info['status'] === null) {
        return '';
    }

    // Do not override manual inactive
    if ($info['recorded'] === 'inactive') {
        return 'inactive';
    }

    if ($info['status'] !== $info['recorded']) {
        $pdo->prepare('UPDATE vehicles SET status = ? WHERE vehicle_id = ?')
            ->execute([$info['status'], $vehicleId]);
    }

    return $info['status'];
}

/**
 * Sync all vehicles that are not inactive.
 * Useful after bulk imports or for a scheduled job.
 */
function syncAllVehicleStatuses(?PDO $pdo = null): int
{
    $pdo = $pdo ?? getPDO();
    $ids = $pdo->query("SELECT vehicle_id FROM vehicles WHERE status != 'inactive'")->fetchAll(PDO::FETCH_COLUMN);
    $count = 0;
    foreach ($ids as $id) {
        syncVehicleStatus((int)$id, $pdo);
        $count++;
    }
    return $count;
}

/**
 * Check if a vehicle is free for a date range (no overlapping
 * confirmed/pending reservations or active rentals, and not in maintenance).
 * Used by the Reservation module before confirming a booking.
 */
function isVehicleAvailableForPeriod(int $vehicleId, string $pickup, string $return, ?int $excludeReservationId = null, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? getPDO();

    // Recorded status blocks
    $v = $pdo->prepare('SELECT status FROM vehicles WHERE vehicle_id = ?');
    $v->execute([$vehicleId]);
    $status = $v->fetchColumn();
    if ($status === false) {
        return ['available' => false, 'reason' => 'Vehicle not found'];
    }
    if ($status === 'inactive') {
        return ['available' => false, 'reason' => 'Vehicle is inactive'];
    }
    if ($status === 'maintenance') {
        // Double-check open maintenance that overlaps period
        $m = $pdo->prepare("
            SELECT COUNT(*) FROM maintenance_records
            WHERE vehicle_id = ?
              AND status IN ('scheduled', 'in_progress')
              AND start_date <= DATE(?)
              AND (expected_completion_date IS NULL OR expected_completion_date >= DATE(?))
        ");
        $m->execute([$vehicleId, $return, $pickup]);
        if ((int)$m->fetchColumn() > 0) {
            return ['available' => false, 'reason' => 'Vehicle has overlapping maintenance'];
        }
    }

    // Overlapping rentals (active / scheduled / overdue)
    $r = $pdo->prepare("
        SELECT COUNT(*) FROM rentals
        WHERE vehicle_id = ?
          AND status IN ('scheduled', 'active', 'overdue')
          AND expected_return_datetime > ?
          AND (actual_pickup_datetime IS NULL OR actual_pickup_datetime < ? OR (actual_pickup_datetime IS NOT NULL AND actual_return_datetime IS NULL))
    ");
    // Simpler overlap: rental period overlaps [pickup, return)
    $r = $pdo->prepare("
        SELECT COUNT(*) FROM rentals
        WHERE vehicle_id = ?
          AND status IN ('scheduled', 'active', 'overdue')
          AND expected_return_datetime > ?
          AND COALESCE(actual_pickup_datetime, expected_return_datetime) < ?
    ");
    // Even simpler reliable overlap using reservation-linked rentals + expected dates
    $r = $pdo->prepare("
        SELECT COUNT(*) FROM rentals r
        JOIN reservations res ON res.reservation_id = r.reservation_id
        WHERE r.vehicle_id = ?
          AND r.status IN ('scheduled', 'active', 'overdue')
          AND res.pickup_datetime < ?
          AND res.return_datetime > ?
    ");
    $r->execute([$vehicleId, $return, $pickup]);
    if ((int)$r->fetchColumn() > 0) {
        return ['available' => false, 'reason' => 'Overlapping active/scheduled rental'];
    }

    // Overlapping reservations (pending or confirmed)
    $sql = "
        SELECT COUNT(*) FROM reservations
        WHERE vehicle_id = ?
          AND status IN ('pending', 'confirmed')
          AND pickup_datetime < ?
          AND return_datetime > ?
    ";
    $params = [$vehicleId, $return, $pickup];
    if ($excludeReservationId) {
        $sql .= ' AND reservation_id != ?';
        $params[] = $excludeReservationId;
    }
    $res = $pdo->prepare($sql);
    $res->execute($params);
    if ((int)$res->fetchColumn() > 0) {
        return ['available' => false, 'reason' => 'Overlapping reservation'];
    }

    // Maintenance overlapping the period
    $m = $pdo->prepare("
        SELECT COUNT(*) FROM maintenance_records
        WHERE vehicle_id = ?
          AND status IN ('scheduled', 'in_progress')
          AND start_date <= DATE(?)
          AND (completed_date IS NULL AND (expected_completion_date IS NULL OR expected_completion_date >= DATE(?)))
    ");
    $m->execute([$vehicleId, $return, $pickup]);
    if ((int)$m->fetchColumn() > 0) {
        return ['available' => false, 'reason' => 'Overlapping maintenance window'];
    }

    return ['available' => true, 'reason' => 'Vehicle is free for this period'];
}

/**
 * Hooks for other modules – call these after state changes.
 */
function onRentalStarted(int $vehicleId): void
{
    syncVehicleStatus($vehicleId);
}

function onRentalReturned(int $vehicleId): void
{
    syncVehicleStatus($vehicleId);
}

function onRentalCancelled(int $vehicleId): void
{
    syncVehicleStatus($vehicleId);
}

function onMaintenanceStarted(int $vehicleId): void
{
    syncVehicleStatus($vehicleId);
}

function onMaintenanceCompleted(int $vehicleId): void
{
    syncVehicleStatus($vehicleId);
}

function onReservationConfirmed(int $vehicleId): void
{
    syncVehicleStatus($vehicleId);
}

function onReservationCancelled(int $vehicleId): void
{
    syncVehicleStatus($vehicleId);
}

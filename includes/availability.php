<?php
/**
 * True if the vehicle can be rented for the period.
 * Pass $excludeReservationId when re-checking an existing reservation (confirm step).
 */
function vehicle_is_available(PDO $pdo, int $vehicleId, string $pickup, string $return, ?int $excludeReservationId = null): bool
{
    // 1. Vehicle must exist and not be in maintenance/inactive
    $s = $pdo->prepare("SELECT status FROM vehicles WHERE vehicle_id = ?");
    $s->execute([$vehicleId]);
    $status = $s->fetchColumn();
    if ($status === false || in_array($status, ['maintenance', 'inactive'], true)) return false;

    // 2. No overlapping CONFIRMED reservation
    $s = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE vehicle_id = :vid AND status = 'confirmed'
           AND reservation_id <> :ex
           AND pickup_datetime < :ret AND return_datetime > :pick"
    );
    $s->execute(['vid' => $vehicleId, 'ex' => $excludeReservationId ?? 0, 'ret' => $return, 'pick' => $pickup]);
    if ((int)$s->fetchColumn() > 0) return false;

    // 3. Not out on an active/overdue rental that is still expected back after pickup
    $s = $pdo->prepare(
        "SELECT COUNT(*) FROM rentals
         WHERE vehicle_id = :vid AND status IN ('active','overdue')
           AND expected_return_datetime > :pick"
    );
    $s->execute(['vid' => $vehicleId, 'pick' => $pickup]);
    if ((int)$s->fetchColumn() > 0) return false;

    // 4. No overlapping maintenance
    $s = $pdo->prepare(
        "SELECT COUNT(*) FROM maintenance_records
         WHERE vehicle_id = :vid AND status IN ('scheduled','in_progress')
           AND start_date <= DATE(:ret)
           AND COALESCE(completed_date, expected_completion_date, '9999-12-31') >= DATE(:pick)"
    );
    $s->execute(['vid' => $vehicleId, 'ret' => $return, 'pick' => $pickup]);
    return (int)$s->fetchColumn() === 0;
}
<?php
/** Confirmed reservations that have no agreement yet (the agreement member's work queue). */
function reservations_ready_for_agreement(PDO $pdo): array
{
    return $pdo->query(
        "SELECT r.*, c.first_name, c.last_name, c.phone, c.driving_license_number,
                v.make, v.model, v.registration_no
         FROM reservations r
         JOIN customers c ON c.customer_id = r.customer_id
         JOIN vehicles v ON v.vehicle_id = r.vehicle_id
         LEFT JOIN rental_agreements a ON a.reservation_id = r.reservation_id
         WHERE r.status = 'confirmed' AND a.agreement_id IS NULL
         ORDER BY r.pickup_datetime"
    )->fetchAll();
}

/** One confirmed reservation with everything an agreement needs, or null. */
function reservation_for_agreement(PDO $pdo, int $reservationId): ?array
{
    $s = $pdo->prepare(
        "SELECT r.*, c.first_name, c.last_name, c.phone, c.email, c.identification_type,
                c.identification_number, c.driving_license_number, c.license_expiry_date,
                v.make, v.model, v.registration_no, v.current_mileage, v.fuel_level_percent
         FROM reservations r
         JOIN customers c ON c.customer_id = r.customer_id
         JOIN vehicles v ON v.vehicle_id = r.vehicle_id
         WHERE r.reservation_id = ? AND r.status = 'confirmed'"
    );
    $s->execute([$reservationId]);
    return $s->fetch() ?: null;
}

/** Upcoming pending/confirmed bookings for a vehicle (for vehicle management). */
function vehicle_bookings(PDO $pdo, int $vehicleId): array
{
    $s = $pdo->prepare(
        "SELECT r.reservation_id, r.reservation_code, r.pickup_datetime, r.return_datetime, r.status,
                c.first_name, c.last_name
         FROM reservations r JOIN customers c ON c.customer_id = r.customer_id
         WHERE r.vehicle_id = ? AND r.status IN ('pending','confirmed') AND r.return_datetime > NOW()
         ORDER BY r.pickup_datetime"
    );
    $s->execute([$vehicleId]);
    return $s->fetchAll();
}

/** Called by the rental pickup process after the rental row is created. */
function reservation_mark_completed(PDO $pdo, int $reservationId): void
{
    $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = ? AND status = 'confirmed'")
        ->execute([$reservationId]);
}
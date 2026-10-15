<?php
// modules/notifications/helpers.php  (Member 5: Eugenie)
// Reuses $pdo, session, e(), csrf_token(), csrf_check(), page_header(), page_footer()
// from the maintenance helpers. Replace with Elias's shared includes when they exist.
require_once __DIR__ . '/../maintenance/helpers.php';

function require_notifications_permission(): void {
    if (!has_permission('notifications.manage')) {
        http_response_code(403);
        exit('Access denied.');
    }
}

/**
 * Insert one notification with status 'queued'.
 * Returns the new notification_id.
 */
function createNotification(
    PDO $pdo,
    ?int $userId,
    ?int $customerId,
    string $type,
    string $subject,
    string $message,
    ?string $entityType = null,
    ?int $entityId = null,
    string $channel = 'in_app'
): int {
    $st = $pdo->prepare(
        "INSERT INTO notifications
         (user_id, customer_id, channel, notification_type, subject, message,
          related_entity_type, related_entity_id, status)
         VALUES (?,?,?,?,?,?,?,?, 'queued')"
    );
    $st->execute([$userId, $customerId, $channel, $type, $subject, $message, $entityType, $entityId]);
    return (int)$pdo->lastInsertId();
}

/** True if a notification of this type already exists for this record. */
function notificationExists(PDO $pdo, string $type, string $entityType, int $entityId): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM notifications
         WHERE notification_type = ? AND related_entity_type = ? AND related_entity_id = ?
         LIMIT 1"
    );
    $st->execute([$type, $entityType, $entityId]);
    return (bool)$st->fetchColumn();
}

/** Create the notification only if it does not exist yet. Returns true if created. */
function createNotificationOnce(
    PDO $pdo, ?int $customerId, string $type, string $subject, string $message,
    string $entityType, int $entityId
): bool {
    if (notificationExists($pdo, $type, $entityType, $entityId)) {
        return false;
    }
    createNotification($pdo, null, $customerId, $type, $subject, $message, $entityType, $entityId);
    return true;
}

function markNotificationRead(PDO $pdo, int $id): void {
    $pdo->prepare(
        "UPDATE notifications SET status = 'read', read_at = NOW()
         WHERE notification_id = ? AND status <> 'read'"
    )->execute([$id]);
}

function markAllNotificationsRead(PDO $pdo): int {
    $st = $pdo->prepare(
        "UPDATE notifications SET status = 'read', read_at = NOW()
         WHERE channel = 'in_app' AND status <> 'read'"
    );
    $st->execute();
    return $st->rowCount();
}

function countUnreadNotifications(PDO $pdo): int {
    return (int)$pdo->query(
        "SELECT COUNT(*) FROM notifications WHERE channel = 'in_app' AND status <> 'read'"
    )->fetchColumn();
}

/**
 * Create booking reminders, return reminders, overdue alerts and payment notifications.
 * Safe to run many times: duplicates are skipped.
 * Returns the number of NEW notifications per kind.
 */
function generateReminders(PDO $pdo): array {
    $made = ['booking_reminder' => 0, 'return_reminder' => 0, 'overdue_alert' => 0, 'payment_notice' => 0];

    // 1. Booking reminders: confirmed reservations with pickup in the next 24 hours
    $rows = $pdo->query(
        "SELECT rs.reservation_id, rs.reservation_code, rs.customer_id, rs.pickup_datetime, v.registration_no
         FROM reservations rs JOIN vehicles v ON v.vehicle_id = rs.vehicle_id
         WHERE rs.status = 'confirmed'
           AND rs.pickup_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)"
    )->fetchAll();
    foreach ($rows as $r) {
        if (createNotificationOnce(
            $pdo, (int)$r['customer_id'], 'booking_reminder',
            'Pickup reminder: ' . $r['reservation_code'],
            "Reservation {$r['reservation_code']} for vehicle {$r['registration_no']} is due for pickup at {$r['pickup_datetime']}.",
            'reservation', (int)$r['reservation_id']
        )) $made['booking_reminder']++;
    }

    // 2. Return reminders: active rentals due back in the next 24 hours
    $rows = $pdo->query(
        "SELECT r.rental_id, r.rental_number, r.customer_id, r.expected_return_datetime, v.registration_no
         FROM rentals r JOIN vehicles v ON v.vehicle_id = r.vehicle_id
         WHERE r.status = 'active'
           AND r.expected_return_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)"
    )->fetchAll();
    foreach ($rows as $r) {
        if (createNotificationOnce(
            $pdo, (int)$r['customer_id'], 'return_reminder',
            'Return reminder: ' . $r['rental_number'],
            "Rental {$r['rental_number']} (vehicle {$r['registration_no']}) must be returned by {$r['expected_return_datetime']}.",
            'rental', (int)$r['rental_id']
        )) $made['return_reminder']++;
    }

    // 3. Overdue alerts: rentals past their expected return time
    $rows = $pdo->query(
        "SELECT r.rental_id, r.rental_number, r.customer_id, r.expected_return_datetime, v.registration_no
         FROM rentals r JOIN vehicles v ON v.vehicle_id = r.vehicle_id
         WHERE r.status IN ('active','overdue') AND r.expected_return_datetime < NOW()"
    )->fetchAll();
    foreach ($rows as $r) {
        if (createNotificationOnce(
            $pdo, (int)$r['customer_id'], 'overdue_alert',
            'OVERDUE: ' . $r['rental_number'],
            "Rental {$r['rental_number']} (vehicle {$r['registration_no']}) was due on {$r['expected_return_datetime']} and has not been returned.",
            'rental', (int)$r['rental_id']
        )) $made['overdue_alert']++;
    }

    // 4. Payment notifications: overdue or partially paid invoices with a balance
    $rows = $pdo->query(
        "SELECT invoice_id, invoice_number, customer_id, due_date, outstanding_balance, status
         FROM vw_invoice_balances
         WHERE status IN ('overdue','partially_paid') AND outstanding_balance > 0"
    )->fetchAll();
    foreach ($rows as $r) {
        $label = $r['status'] === 'overdue' ? 'Overdue invoice' : 'Partially paid invoice';
        if (createNotificationOnce(
            $pdo, (int)$r['customer_id'], 'payment_' . $r['status'],
            $label . ': ' . $r['invoice_number'],
            "$label {$r['invoice_number']} has an outstanding balance of " . number_format((float)$r['outstanding_balance'], 2) . " RWF" .
            ($r['due_date'] ? " (due {$r['due_date']})." : '.'),
            'invoice', (int)$r['invoice_id']
        )) $made['payment_notice']++;
    }

    return $made;
}
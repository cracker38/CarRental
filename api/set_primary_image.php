<?php
/**
 * Set vehicle image as primary – only Business Manager / Rental Officer
 * (System Administrator is excluded per business rule)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireLogin();

// Only operational managers – not System Administrator
$role = $_SESSION['role_name'] ?? '';
$allowedRoles = ['Business Manager', 'Rental Officer'];
if (!in_array($role, $allowedRoles, true)) {
    jsonResponse(['success' => false, 'message' => 'Only Business Manager or Rental Officer can manage vehicle images.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$imageId   = (int)($input['image_id'] ?? 0);
$vehicleId = (int)($input['vehicle_id'] ?? 0);

if ($imageId <= 0 || $vehicleId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT image_id FROM vehicle_images WHERE image_id = ? AND vehicle_id = ?');
    $check->execute([$imageId, $vehicleId]);
    if (!$check->fetch()) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Image not found'], 404);
    }

    $pdo->prepare('UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = ?')->execute([$vehicleId]);
    $pdo->prepare('UPDATE vehicle_images SET is_primary = 1 WHERE image_id = ?')->execute([$imageId]);

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Primary image updated']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

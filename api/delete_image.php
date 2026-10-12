<?php
/**
 * Delete a vehicle image – only Business Manager / Rental Officer
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
requireLogin();

$role = $_SESSION['role_name'] ?? '';
if (!in_array($role, ['Business Manager', 'Rental Officer'], true)) {
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
    $sel = $pdo->prepare('SELECT image_path, is_primary FROM vehicle_images WHERE image_id = ? AND vehicle_id = ?');
    $sel->execute([$imageId, $vehicleId]);
    $row = $sel->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Image not found'], 404);
    }

    $path = __DIR__ . '/../uploads/vehicles/' . $row['image_path'];
    if (is_file($path)) {
        unlink($path);
    }

    $pdo->prepare('DELETE FROM vehicle_images WHERE image_id = ? AND vehicle_id = ?')->execute([$imageId, $vehicleId]);

    // If deleted primary, promote another image
    if ((int)$row['is_primary'] === 1) {
        $next = $pdo->prepare('SELECT image_id FROM vehicle_images WHERE vehicle_id = ? ORDER BY image_id LIMIT 1');
        $next->execute([$vehicleId]);
        $nid = $next->fetchColumn();
        if ($nid) {
            $pdo->prepare('UPDATE vehicle_images SET is_primary = 1 WHERE image_id = ?')->execute([$nid]);
        }
    }

    jsonResponse(['success' => true, 'message' => 'Image deleted']);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

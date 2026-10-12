<?php
/**
 * Replace an existing vehicle image file (same image_id, new file).
 * Business Manager / Rental Officer only.
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

$imageId   = (int)($_POST['image_id'] ?? 0);
$vehicleId = (int)($_POST['vehicle_id'] ?? 0);

if ($imageId <= 0 || $vehicleId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No image file uploaded'], 400);
}

$tmp  = $_FILES['image']['tmp_name'];
$mime = mime_content_type($tmp);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
if (!isset($allowed[$mime])) {
    jsonResponse(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG, WebP or GIF.'], 400);
}

try {
    $pdo = getPDO();
    $sel = $pdo->prepare('SELECT image_path, is_primary FROM vehicle_images WHERE image_id = ? AND vehicle_id = ?');
    $sel->execute([$imageId, $vehicleId]);
    $row = $sel->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Image not found'], 404);
    }

    $dir = __DIR__ . '/../uploads/vehicles/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ext = $allowed[$mime];
    $newName = 'veh_' . $vehicleId . '_' . uniqid() . '.' . $ext;

    if (!move_uploaded_file($tmp, $dir . $newName)) {
        jsonResponse(['success' => false, 'message' => 'Failed to save uploaded file'], 500);
    }

    // Remove old file from disk
    $oldPath = $dir . $row['image_path'];
    if (is_file($oldPath) && $row['image_path'] !== $newName) {
        @unlink($oldPath);
    }

    // Keep same image_id and is_primary — only path changes
    $pdo->prepare('UPDATE vehicle_images SET image_path = ? WHERE image_id = ? AND vehicle_id = ?')
        ->execute([$newName, $imageId, $vehicleId]);

    jsonResponse([
        'success' => true,
        'message' => 'Image replaced successfully',
        'image_path' => $newName,
        'url' => BASE_URL . '/uploads/vehicles/' . $newName,
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

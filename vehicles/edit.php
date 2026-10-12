<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission('vehicles.manage');
$pageTitle = 'Edit Vehicle';
$errors = [];
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/vehicles/index.php');
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT * FROM vehicles WHERE vehicle_id = ?');
$stmt->execute([$id]);
$vehicle = $stmt->fetch();
if (!$vehicle) { setFlash('error','Vehicle not found.'); redirect('/vehicles/index.php'); }

$categories = $pdo->query("SELECT category_id, category_name FROM vehicle_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll();
$imgStmt = $pdo->prepare('SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC, image_id');
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

$data = [
    'category_id'=>$vehicle['category_id'],'registration_no'=>$vehicle['registration_no'],'vin'=>$vehicle['vin']??'',
    'make'=>$vehicle['make'],'model'=>$vehicle['model'],'manufacture_year'=>$vehicle['manufacture_year']??'',
    'colour'=>$vehicle['colour']??'','transmission'=>$vehicle['transmission'],'fuel_type'=>$vehicle['fuel_type'],
    'passenger_capacity'=>$vehicle['passenger_capacity']??'','luggage_capacity'=>$vehicle['luggage_capacity']??'',
    'daily_rate'=>$vehicle['daily_rate'],'weekly_rate'=>$vehicle['weekly_rate']??'','monthly_rate'=>$vehicle['monthly_rate']??'',
    'current_mileage'=>$vehicle['current_mileage'],'fuel_level_percent'=>$vehicle['fuel_level_percent']??'',
    'status'=>$vehicle['status'],'insurance_policy_no'=>$vehicle['insurance_policy_no']??'',
    'insurance_expiry_date'=>$vehicle['insurance_expiry_date']??'','inspection_expiry_date'=>$vehicle['inspection_expiry_date']??'',
    'description'=>$vehicle['description']??''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) { if (isset($_POST[$k])) $data[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]; }
    if ($data['registration_no'] === '') $errors['registration_no'] = 'Required.';
    if ($data['make'] === '') $errors['make'] = 'Required.';
    if ($data['model'] === '') $errors['model'] = 'Required.';
    if ($data['daily_rate'] === '' || !is_numeric($data['daily_rate'])) $errors['daily_rate'] = 'Valid rate required.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE vehicles SET category_id=?,registration_no=?,vin=?,make=?,model=?,manufacture_year=?,colour=?,transmission=?,fuel_type=?,passenger_capacity=?,luggage_capacity=?,daily_rate=?,weekly_rate=?,monthly_rate=?,current_mileage=?,fuel_level_percent=?,status=?,insurance_policy_no=?,insurance_expiry_date=?,inspection_expiry_date=?,description=? WHERE vehicle_id=?")
                ->execute([
                    (int)$data['category_id'],$data['registration_no'],$data['vin']?:null,$data['make'],$data['model'],
                    $data['manufacture_year']!==''?(int)$data['manufacture_year']:null,$data['colour']?:null,
                    $data['transmission'],$data['fuel_type'],
                    $data['passenger_capacity']!==''?(int)$data['passenger_capacity']:null,
                    $data['luggage_capacity']!==''?(int)$data['luggage_capacity']:null,
                    $data['daily_rate'],$data['weekly_rate']!==''?$data['weekly_rate']:null,$data['monthly_rate']!==''?$data['monthly_rate']:null,
                    (int)$data['current_mileage'],$data['fuel_level_percent']!==''?$data['fuel_level_percent']:null,
                    $data['status'],$data['insurance_policy_no']?:null,$data['insurance_expiry_date']?:null,$data['inspection_expiry_date']?:null,
                    $data['description']?:null,$id
                ]);
            if (canManageVehicleImages() && !empty($_FILES['images']['name'][0])) {
                $dir = __DIR__.'/../uploads/vehicles/';
                if (!is_dir($dir)) mkdir($dir,0755,true);
                $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                $hasP = (bool)$pdo->query("SELECT 1 FROM vehicle_images WHERE vehicle_id=$id AND is_primary=1")->fetch();
                foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if (!in_array(mime_content_type($tmp), $allowed, true)) continue;
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    $fn = 'veh_'.$id.'_'.uniqid().'.'.$ext;
                    if (move_uploaded_file($tmp, $dir.$fn)) {
                        $pdo->prepare("INSERT INTO vehicle_images (vehicle_id,image_path,is_primary) VALUES (?,?,?)")->execute([$id,$fn,$hasP?0:1]);
                        $hasP = true;
                    }
                }
            }
            if (canManageVehicleImages() && !empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                $sel = $pdo->prepare('SELECT image_path FROM vehicle_images WHERE image_id=? AND vehicle_id=?');
                $del = $pdo->prepare('DELETE FROM vehicle_images WHERE image_id=? AND vehicle_id=?');
                foreach ($_POST['delete_images'] as $iid) {
                    $sel->execute([(int)$iid, $id]);
                    $row = $sel->fetch();
                    if ($row) {
                        $p = __DIR__.'/../uploads/vehicles/'.$row['image_path'];
                        if (is_file($p)) unlink($p);
                        $del->execute([(int)$iid, $id]);
                    }
                }
            }
            $pdo->commit();
            setFlash('success','Vehicle updated.');
            redirect('/vehicles/details.php?id='.$id);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['_general'] = ($e->getCode()==23000) ? 'Duplicate registration/VIN.' : $e->getMessage();
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h1><i class="fas fa-edit"></i> Edit – <?= e($vehicle['registration_no']) ?></h1>
<a href="details.php?id=<?= $id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body">
<?php if (!empty($errors['_general'])): ?><div class="alert alert-error"><?= e($errors['_general']) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <div class="form-row">
        <div class="form-group"><label>Category</label><select name="category_id" class="form-control"><?php foreach($categories as $c):?><option value="<?=$c['category_id']?>" <?= $data['category_id']==$c['category_id']?'selected':''?>><?=e($c['category_name'])?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Registration <span class="required">*</span></label><input type="text" name="registration_no" class="form-control" value="<?= e($data['registration_no']) ?>" required></div>
        <div class="form-group"><label>VIN</label><input type="text" name="vin" class="form-control" value="<?= e($data['vin']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Make <span class="required">*</span></label><input type="text" name="make" class="form-control" value="<?= e($data['make']) ?>" required></div>
        <div class="form-group"><label>Model <span class="required">*</span></label><input type="text" name="model" class="form-control" value="<?= e($data['model']) ?>" required></div>
        <div class="form-group"><label>Year</label><input type="number" name="manufacture_year" class="form-control" value="<?= e($data['manufacture_year']) ?>"></div>
        <div class="form-group"><label>Colour</label><input type="text" name="colour" class="form-control" value="<?= e($data['colour']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Transmission</label><select name="transmission" class="form-control"><?php foreach(['manual','automatic','other'] as $t):?><option value="<?=$t?>" <?= $data['transmission']===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Fuel</label><select name="fuel_type" class="form-control"><?php foreach(['petrol','diesel','hybrid','electric','other'] as $f):?><option value="<?=$f?>" <?= $data['fuel_type']===$f?'selected':''?>><?=ucfirst($f)?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Passengers</label><input type="number" name="passenger_capacity" class="form-control" value="<?= e($data['passenger_capacity']) ?>"></div>
        <div class="form-group"><label>Luggage</label><input type="number" name="luggage_capacity" class="form-control" value="<?= e($data['luggage_capacity']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Daily Rate <span class="required">*</span></label><input type="number" name="daily_rate" step="0.01" min="0" class="form-control" value="<?= e($data['daily_rate']) ?>" required></div>
        <div class="form-group"><label>Weekly</label><input type="number" name="weekly_rate" step="0.01" class="form-control" value="<?= e($data['weekly_rate']) ?>"></div>
        <div class="form-group"><label>Monthly</label><input type="number" name="monthly_rate" step="0.01" class="form-control" value="<?= e($data['monthly_rate']) ?>"></div>
        <div class="form-group"><label>Status</label><select name="status" class="form-control"><?php foreach(vehicleStatuses() as $s):?><option value="<?=$s?>" <?= $data['status']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Mileage</label><input type="number" name="current_mileage" class="form-control" value="<?= e($data['current_mileage']) ?>"></div>
        <div class="form-group"><label>Fuel %</label><input type="number" name="fuel_level_percent" class="form-control" min="0" max="100" value="<?= e($data['fuel_level_percent']) ?>"></div>
        <div class="form-group"><label>Policy No</label><input type="text" name="insurance_policy_no" class="form-control" value="<?= e($data['insurance_policy_no']) ?>"></div>
        <div class="form-group"><label>Insurance Expiry</label><input type="date" name="insurance_expiry_date" class="form-control" value="<?= e($data['insurance_expiry_date']) ?>"></div>
    </div>
    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"><?= e($data['description']) ?></textarea></div>
    <?php if (canManageVehicleImages()): ?>
    <h3 style="margin:1rem 0 .7rem;font-size:1.05rem">Vehicle Images <small style="font-weight:400;color:var(--gray-500)">(Manager only)</small></h3>
    <?php if ($images): ?>
    <div class="gallery" style="margin-bottom:1rem" id="imageGallery">
    <?php foreach ($images as $img): ?>
        <div class="img-manage-item" data-image-id="<?= (int)$img['image_id'] ?>">
            <img src="<?= BASE_URL ?>/uploads/vehicles/<?= e($img['image_path']) ?>"
                 class="<?= $img['is_primary'] ? 'primary' : '' ?>"
                 alt="Vehicle">
            <div style="display:flex;flex-direction:column;gap:.25rem;margin-top:.3rem">
                <?php if ($img['is_primary']): ?>
                    <span class="status status-available" style="text-align:center">Primary</span>
                <?php else: ?>
                    <button type="button" class="btn btn-outline btn-sm btn-set-primary"
                            data-image-id="<?= (int)$img['image_id'] ?>"
                            data-vehicle-id="<?= (int)$id ?>">
                        <i class="fas fa-star"></i> Set primary
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-danger btn-sm btn-delete-image"
                        data-image-id="<?= (int)$img['image_id'] ?>"
                        data-vehicle-id="<?= (int)$id ?>">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <label style="font-size:.7rem"><input type="checkbox" name="delete_images[]" value="<?= (int)$img['image_id'] ?>"> Mark for delete on save</label>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p style="color:var(--gray-500);margin-bottom:.75rem;font-size:.9rem">No images yet.</p>
    <?php endif; ?>
    <div class="upload-zone" id="uploadZone"><i class="fas fa-cloud-upload-alt"></i><p>Click or drag new images (JPG, PNG, WebP)</p>
        <input type="file" name="images[]" id="imageFiles" accept="image/*" multiple style="display:none"></div>
    <div class="image-preview-list" id="imagePreviewList"></div>
    <?php else: ?>
    <div class="alert alert-error" style="background:#fef3c7;color:#92400e;border-color:#fde68a">
        <i class="fas fa-info-circle"></i> Only <strong>Business Manager</strong> or <strong>Rental Officer</strong> can edit vehicle images.
        <?php if ($images): ?>
        <div class="gallery" style="margin-top:.75rem">
            <?php foreach ($images as $img): ?>
                <img src="<?= BASE_URL ?>/uploads/vehicles/<?= e($img['image_path']) ?>" class="<?= $img['is_primary']?'primary':'' ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
        <a href="details.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission('vehicles.manage');
$pageTitle = 'Register Vehicle';
$errors = [];
$pdo = getPDO();
$categories = $pdo->query("SELECT category_id, category_name, default_daily_rate FROM vehicle_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll();

$data = ['category_id'=>'','registration_no'=>'','vin'=>'','make'=>'','model'=>'','manufacture_year'=>'','colour'=>'',
    'transmission'=>'manual','fuel_type'=>'petrol','passenger_capacity'=>'','luggage_capacity'=>'',
    'daily_rate'=>'','weekly_rate'=>'','monthly_rate'=>'','current_mileage'=>'0','fuel_level_percent'=>'',
    'status'=>'available','insurance_policy_no'=>'','insurance_expiry_date'=>'','inspection_expiry_date'=>'','description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) { if (isset($_POST[$k])) $data[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]; }
    if ($data['category_id'] === '') $errors['category_id'] = 'Category required.';
    if ($data['registration_no'] === '') $errors['registration_no'] = 'Registration required.';
    if ($data['make'] === '') $errors['make'] = 'Make required.';
    if ($data['model'] === '') $errors['model'] = 'Model required.';
    if ($data['daily_rate'] === '' || !is_numeric($data['daily_rate']) || $data['daily_rate'] < 0) $errors['daily_rate'] = 'Valid daily rate required.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO vehicles (category_id,registration_no,vin,make,model,manufacture_year,colour,transmission,fuel_type,passenger_capacity,luggage_capacity,daily_rate,weekly_rate,monthly_rate,current_mileage,fuel_level_percent,status,insurance_policy_no,insurance_expiry_date,inspection_expiry_date,description,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                (int)$data['category_id'], $data['registration_no'], $data['vin']?:null, $data['make'], $data['model'],
                $data['manufacture_year']!==''?(int)$data['manufacture_year']:null, $data['colour']?:null,
                $data['transmission'], $data['fuel_type'],
                $data['passenger_capacity']!==''?(int)$data['passenger_capacity']:null,
                $data['luggage_capacity']!==''?(int)$data['luggage_capacity']:null,
                $data['daily_rate'], $data['weekly_rate']!==''?$data['weekly_rate']:null, $data['monthly_rate']!==''?$data['monthly_rate']:null,
                (int)$data['current_mileage'], $data['fuel_level_percent']!==''?$data['fuel_level_percent']:null,
                $data['status'], $data['insurance_policy_no']?:null, $data['insurance_expiry_date']?:null, $data['inspection_expiry_date']?:null,
                $data['description']?:null, $_SESSION['user_id'] ?? null
            ]);
            $vid = (int)$pdo->lastInsertId();
            if (canManageVehicleImages() && !empty($_FILES['images']['name'][0])) {
                $dir = __DIR__ . '/../uploads/vehicles/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                // One image only
                $tmp = $_FILES['images']['tmp_name'][0];
                if ($_FILES['images']['error'][0] === UPLOAD_ERR_OK && in_array(mime_content_type($tmp), $allowed, true)) {
                    $ext = strtolower(pathinfo($_FILES['images']['name'][0], PATHINFO_EXTENSION));
                    $fn = 'veh_' . $vid . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmp, $dir . $fn)) {
                        $pdo->prepare("INSERT INTO vehicle_images (vehicle_id,image_path,is_primary,uploaded_by) VALUES (?,?,1,?)")
                            ->execute([$vid, $fn, $_SESSION['user_id'] ?? null]);
                    }
                }
            }
            $pdo->commit();
            setFlash('success', 'Vehicle registered successfully.');
            redirect('/vehicles/details.php?id=' . $vid);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['_general'] = ($e->getCode()==23000) ? 'Registration or VIN already exists.' : $e->getMessage();
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h1><i class="fas fa-plus-circle"></i> Register Vehicle</h1>
<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body">
<?php if (!empty($errors['_general'])): ?><div class="alert alert-error"><?= e($errors['_general']) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <h3 style="margin-bottom:.9rem;font-size:1.05rem">Basic Information</h3>
    <div class="form-row">
        <div class="form-group"><label>Category <span class="required">*</span></label>
            <select name="category_id" id="catSelect" class="form-control <?= isset($errors['category_id'])?'is-invalid':'' ?>" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['category_id'] ?>" data-rate="<?= $c['default_daily_rate'] ?>" <?= $data['category_id']==$c['category_id']?'selected':'' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
            </select>
            <?php if(isset($errors['category_id'])):?><div class="invalid-feedback"><?=$errors['category_id']?></div><?php endif;?>
        </div>
        <div class="form-group"><label>Registration No <span class="required">*</span></label>
            <input type="text" name="registration_no" class="form-control <?= isset($errors['registration_no'])?'is-invalid':'' ?>" value="<?= e($data['registration_no']) ?>" required>
            <?php if(isset($errors['registration_no'])):?><div class="invalid-feedback"><?=$errors['registration_no']?></div><?php endif;?>
        </div>
        <div class="form-group"><label>VIN</label><input type="text" name="vin" class="form-control" value="<?= e($data['vin']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Make <span class="required">*</span></label><input type="text" name="make" class="form-control" value="<?= e($data['make']) ?>" required></div>
        <div class="form-group"><label>Model <span class="required">*</span></label><input type="text" name="model" class="form-control" value="<?= e($data['model']) ?>" required></div>
        <div class="form-group"><label>Year</label><input type="number" name="manufacture_year" class="form-control" value="<?= e($data['manufacture_year']) ?>" min="1980" max="<?= date('Y')+1 ?>"></div>
        <div class="form-group"><label>Colour</label><input type="text" name="colour" class="form-control" value="<?= e($data['colour']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Transmission</label><select name="transmission" class="form-control"><?php foreach(['manual','automatic','other'] as $t):?><option value="<?=$t?>" <?= $data['transmission']===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Fuel</label><select name="fuel_type" class="form-control"><?php foreach(['petrol','diesel','hybrid','electric','other'] as $f):?><option value="<?=$f?>" <?= $data['fuel_type']===$f?'selected':''?>><?=ucfirst($f)?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Passengers</label><input type="number" name="passenger_capacity" class="form-control" value="<?= e($data['passenger_capacity']) ?>"></div>
        <div class="form-group"><label>Luggage</label><input type="number" name="luggage_capacity" class="form-control" value="<?= e($data['luggage_capacity']) ?>"></div>
    </div>
    <h3 style="margin:1.2rem 0 .9rem;font-size:1.05rem">Rates & Status</h3>
    <div class="form-row">
        <div class="form-group"><label>Daily Rate (RWF) <span class="required">*</span></label>
            <input type="number" name="daily_rate" id="dailyRate" step="0.01" min="0" class="form-control <?= isset($errors['daily_rate'])?'is-invalid':'' ?>" value="<?= e($data['daily_rate']) ?>" required>
            <?php if(isset($errors['daily_rate'])):?><div class="invalid-feedback"><?=$errors['daily_rate']?></div><?php endif;?>
        </div>
        <div class="form-group"><label>Weekly Rate</label><input type="number" name="weekly_rate" step="0.01" min="0" class="form-control" value="<?= e($data['weekly_rate']) ?>"></div>
        <div class="form-group"><label>Monthly Rate</label><input type="number" name="monthly_rate" step="0.01" min="0" class="form-control" value="<?= e($data['monthly_rate']) ?>"></div>
        <div class="form-group"><label>Status</label><select name="status" class="form-control"><?php foreach(vehicleStatuses() as $s):?><option value="<?=$s?>" <?= $data['status']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Mileage (km)</label><input type="number" name="current_mileage" class="form-control" value="<?= e($data['current_mileage']) ?>"></div>
        <div class="form-group"><label>Fuel Level %</label><input type="number" name="fuel_level_percent" class="form-control" min="0" max="100" step="0.01" value="<?= e($data['fuel_level_percent']) ?>"></div>
    </div>
    <h3 style="margin:1.2rem 0 .9rem;font-size:1.05rem">Insurance & Inspection</h3>
    <div class="form-row">
        <div class="form-group"><label>Policy No</label><input type="text" name="insurance_policy_no" class="form-control" value="<?= e($data['insurance_policy_no']) ?>"></div>
        <div class="form-group"><label>Insurance Expiry</label><input type="date" name="insurance_expiry_date" class="form-control" value="<?= e($data['insurance_expiry_date']) ?>"></div>
        <div class="form-group"><label>Inspection Expiry</label><input type="date" name="inspection_expiry_date" class="form-control" value="<?= e($data['inspection_expiry_date']) ?>"></div>
    </div>
    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= e($data['description']) ?></textarea></div>
    <?php if (canManageVehicleImages()): ?>
    <h3 style="margin:1.2rem 0 .9rem;font-size:1.05rem">Images <small style="font-weight:400;color:var(--gray-500)">(Manager only)</small></h3>
    <div class="upload-zone" id="uploadZone"><i class="fas fa-cloud-upload-alt"></i><p>Click or drag images (JPG, PNG, WebP)</p>
        <input type="file" name="images[]" id="imageFiles" accept="image/*" style="display:none"></div>
    <div class="image-preview-list" id="imagePreviewList"></div>
    <?php else: ?>
    <p style="color:var(--gray-500);font-size:.9rem;margin:1rem 0">Image upload is available only to Business Manager or Rental Officer.</p>
    <?php endif; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Register Vehicle</button>
        <a href="index.php" class="btn btn-outline">Cancel</a>
    </div>
</form>
</div></div>
<script>
document.getElementById('catSelect')?.addEventListener('change', function(){
    const rate = this.options[this.selectedIndex].dataset.rate;
    const el = document.getElementById('dailyRate');
    if (rate && el && !el.value) el.value = rate;
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
start_session();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = array_map('trim', $_POST);

    $first = $old['first_name'] ?? '';
    $last  = $old['last_name'] ?? '';
    $phone = $old['phone'] ?? '';
    $email = $old['email'] ?? '';
    $idType = $old['identification_type'] ?? '';
    $idNum  = $old['identification_number'] ?? '';
    $license = $old['driving_license_number'] ?? '';
    $licExp  = $old['license_expiry_date'] ?? '';
    $dob     = $old['date_of_birth'] ?? '';

    if ($first === '' || strlen($first) > 100) $errors[] = 'First name is required (max 100 characters).';
    if ($last === ''  || strlen($last) > 100)  $errors[] = 'Last name is required (max 100 characters).';
    if (!preg_match('/^\+?[0-9 ]{9,15}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!in_array($idType, ['national_id', 'passport', 'other'], true)) $errors[] = 'Select an identification type.';
    if ($idNum === '') $errors[] = 'Identification number is required.';
    if ($license === '') $errors[] = 'Driving license number is required.';
    if (!valid_date($licExp) || $licExp <= date('Y-m-d')) $errors[] = 'License expiry date must be a valid future date.';
    if ($dob !== '') {
        if (!valid_date($dob)) $errors[] = 'Invalid date of birth.';
        elseif ((new DateTime($dob))->diff(new DateTime())->y < 18) $errors[] = 'Customer must be at least 18 years old.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO customers
                 (customer_code, first_name, last_name, phone, email, address_line, city,
                  identification_type, identification_number, driving_license_number,
                  license_expiry_date, date_of_birth, created_by)
                 VALUES (:code, :first, :last, :phone, :email, :addr, :city,
                         :idt, :idn, :lic, :licexp, :dob, :by)"
            );
            $stmt->execute([
                'code' => 'TMP' . bin2hex(random_bytes(8)), // replaced below
                'first' => $first, 'last' => $last, 'phone' => $phone,
                'email' => nullable($email),
                'addr' => nullable($old['address_line'] ?? ''),
                'city' => nullable($old['city'] ?? ''),
                'idt' => $idType, 'idn' => $idNum, 'lic' => $license,
                'licexp' => $licExp, 'dob' => nullable($dob),
                'by' => current_user_id(),
            ]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE customers SET customer_code = ? WHERE customer_id = ?")
                ->execute([sprintf('CUS-%06d', $id), $id]);
            $pdo->commit();

            flash('Customer registered successfully.');
            redirect('index.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $errors[] = 'A customer with this identification number already exists.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'Could not save the customer. Please try again.';
            }
        }
    }
}
?>
<?php $pageTitle = 'Register customer'; require_once __DIR__ . '/../../includes/header.php'; ?>
<h1>Register Customer</h1>

<?php foreach ($errors as $err): ?>
  <p style="color:red"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?>
  <p><label>First name <input name="first_name" value="<?= e($old['first_name'] ?? '') ?>" required></label></p>
  <p><label>Last name <input name="last_name" value="<?= e($old['last_name'] ?? '') ?>" required></label></p>
  <p><label>Phone <input name="phone" value="<?= e($old['phone'] ?? '') ?>" required></label></p>
  <p><label>Email <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>"></label></p>
  <p><label>ID type
    <select name="identification_type" required>
      <option value="">-- select --</option>
      <?php foreach (['national_id' => 'National ID', 'passport' => 'Passport', 'other' => 'Other'] as $v => $l): ?>
        <option value="<?= $v ?>" <?= ($old['identification_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select></label></p>
  <p><label>ID number <input name="identification_number" value="<?= e($old['identification_number'] ?? '') ?>" required></label></p>
  <p><label>Driving license no. <input name="driving_license_number" value="<?= e($old['driving_license_number'] ?? '') ?>" required></label></p>
  <p><label>License expiry <input type="date" name="license_expiry_date" value="<?= e($old['license_expiry_date'] ?? '') ?>" required></label></p>
  <p><label>Date of birth <input type="date" name="date_of_birth" value="<?= e($old['date_of_birth'] ?? '') ?>"></label></p>
  <p><label>Address <input name="address_line" value="<?= e($old['address_line'] ?? '') ?>"></label></p>
  <p><label>City <input name="city" value="<?= e($old['city'] ?? '') ?>"></label></p>
  <button type="submit">Register</button>
  <a href="index.php">Cancel</a>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


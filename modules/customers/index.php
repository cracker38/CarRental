<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
start_session();

$q = trim($_GET['q'] ?? '');
$sql = "SELECT customer_id, customer_code, first_name, last_name, phone, email, status
        FROM customers";
$params = [];

if ($q !== '') {
    $sql .= " WHERE first_name LIKE :q1 OR last_name LIKE :q2
              OR CONCAT(first_name, ' ', last_name) LIKE :q3
              OR phone LIKE :q4 OR customer_code LIKE :q5
              OR identification_number LIKE :q6";
    for ($i = 1; $i <= 6; $i++) $params["q$i"] = "%$q%";
}
$sql .= " ORDER BY created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Customers</title></head>
<body>
<h1>Customers</h1>
<?php if ($flash): ?><p style="color:green"><?= e($flash) ?></p><?php endif; ?>

<form method="get">
  <input name="q" placeholder="Name, phone, code or ID number" value="<?= e($q) ?>">
  <button>Search</button>
  <a href="register.php">+ New customer</a>
</form>

<table border="1" cellpadding="6">
  <tr><th>Code</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th></tr>
  <?php foreach ($customers as $c): ?>
  <tr>
    <td><?= e($c['customer_code']) ?></td>
    <td><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td>
    <td><?= e($c['phone']) ?></td>
    <td><?= e($c['email']) ?></td>
    <td><?= e($c['status']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$customers): ?><tr><td colspan="5">No customers found.</td></tr><?php endif; ?>
</table>
</body>
</html>
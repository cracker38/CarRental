<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
start_session();
require_permission($pdo, 'notifications.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nid = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($nid) {
        $pdo->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE notification_id = ?")
            ->execute([$nid]);
    }
    redirect('index.php');
}

$filter = $_GET['show'] ?? 'unread';
$sql = "SELECT n.notification_id, n.subject, n.message, n.status, n.created_at,
               n.related_entity_type, n.related_entity_id,
               c.first_name, c.last_name
        FROM notifications n
        LEFT JOIN customers c ON c.customer_id = n.customer_id";
if ($filter === 'unread') $sql .= " WHERE n.status <> 'read'";
$sql .= " ORDER BY n.created_at DESC LIMIT 100";
$rows = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Notifications</title></head>
<body>
<h1>Notifications</h1>
<p>
  <a href="?show=unread">Unread</a> | <a href="?show=all">All</a> |
  <a href="../reservations/index.php">Reservations</a>
</p>
<table border="1" cellpadding="6">
  <tr><th>Date</th><th>Customer</th><th>Subject</th><th>Message</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $n): ?>
  <tr>
    <td><?= e($n['created_at']) ?></td>
    <td><?= e(trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? ''))) ?></td>
    <td><?= e($n['subject']) ?></td>
    <td>
      <?php if ($n['related_entity_type'] === 'reservation'): ?>
        <a href="../reservations/view.php?id=<?= (int)$n['related_entity_id'] ?>"><?= e($n['message']) ?></a>
      <?php else: ?>
        <?= e($n['message']) ?>
      <?php endif; ?>
    </td>
    <td><?= e($n['status']) ?></td>
    <td>
      <?php if ($n['status'] !== 'read'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$n['notification_id'] ?>">
        <button>Mark read</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6">No notifications.</td></tr><?php endif; ?>
</table>
</body>
</html>
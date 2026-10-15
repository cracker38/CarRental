<?php
// modules/notifications/list.php  (Member 5: Eugenie)
require_once __DIR__ . '/helpers.php';
require_notifications_permission();

// Mark one / mark all as read (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'read_one') {
        markNotificationRead($pdo, (int)($_POST['id'] ?? 0));
        $_SESSION['flash'] = 'Notification marked as read.';
    } elseif ($action === 'read_all') {
        $n = markAllNotificationsRead($pdo);
        $_SESSION['flash'] = "$n notification(s) marked as read.";
    }
    header('Location: list.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}

$filter = $_GET['filter'] ?? 'unread';
if (!in_array($filter, ['unread', 'read', 'all'], true)) {
    $filter = 'unread';
}

$sql = "SELECT n.notification_id, n.notification_type, n.subject, n.message, n.status, n.created_at, n.read_at,
               CONCAT(c.first_name, ' ', c.last_name) AS customer
        FROM notifications n
        LEFT JOIN customers c ON c.customer_id = n.customer_id
        WHERE n.channel = 'in_app'";
if ($filter === 'unread') $sql .= " AND n.status <> 'read'";
if ($filter === 'read')   $sql .= " AND n.status = 'read'";
$sql .= " ORDER BY n.created_at DESC, n.notification_id DESC LIMIT 200";

$rows   = $pdo->query($sql)->fetchAll();
$unread = countUnreadNotifications($pdo);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function type_badge(string $t): string {
    $map = [
        'booking_reminder'        => 'primary',
        'return_reminder'         => 'info',
        'overdue_alert'           => 'danger',
        'payment_overdue'         => 'danger',
        'payment_partially_paid'  => 'warning',
    ];
    return '<span class="badge text-bg-' . ($map[$t] ?? 'secondary') . '">' . e(str_replace('_', ' ', $t)) . '</span>';
}

page_header('Notifications');
?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Notifications <span class="badge text-bg-danger"><?= (int)$unread ?> unread</span></h1>
    <div class="d-flex gap-2">
      <form method="post" action="send_reminders.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-primary">Generate reminders</button>
      </form>
      <form method="post" action="list.php?filter=<?= e($filter) ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="read_all">
        <button class="btn btn-outline-secondary">Mark all as read</button>
      </form>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
  <?php endif; ?>

  <ul class="nav nav-pills mb-3">
    <?php foreach (['unread' => 'Unread', 'read' => 'Read', 'all' => 'All'] as $k => $label): ?>
      <li class="nav-item"><a class="nav-link <?= $filter === $k ? 'active' : '' ?>" href="list.php?filter=<?= e($k) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr><th>When</th><th>Type</th><th>Subject / message</th><th>Customer</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-muted">No notifications here.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $n): $isRead = $n['status'] === 'read'; ?>
          <tr class="<?= $isRead ? '' : 'table-warning' ?>">
            <td class="text-nowrap"><?= e($n['created_at']) ?></td>
            <td><?= type_badge($n['notification_type']) ?></td>
            <td>
              <div class="fw-semibold"><?= e($n['subject']) ?></div>
              <div class="small text-muted"><?= e($n['message']) ?></div>
            </td>
            <td><?= e($n['customer'] ?? '-') ?></td>
            <td><?= $isRead ? 'read' : 'unread' ?></td>
            <td>
              <?php if (!$isRead): ?>
                <form method="post" action="list.php?filter=<?= e($filter) ?>">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="read_one">
                  <input type="hidden" name="id" value="<?= (int)$n['notification_id'] ?>">
                  <button class="btn btn-sm btn-outline-primary">Mark as read</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php page_footer(); ?>
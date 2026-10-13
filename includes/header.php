
<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$root    = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$base    = substr($root, strlen($docRoot));
$pageTitle = $pageTitle ?? 'CRMS';
$section = basename(dirname($_SERVER['SCRIPT_FILENAME']));
$script  = basename($_SERVER['SCRIPT_FILENAME']);
$active = fn(bool $on) => $on ? ' class="active"' : '';

$unread = 0;
if (isset($pdo)) {
    try { $unread = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE status <> 'read'")->fetchColumn(); }
    catch (Throwable $t) { $unread = 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | CRMS</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= $base ?>/dashboard.php"><span class="brand-icon">&#128663;</span> CRMS</a>
    <div class="user-box">
      <div class="avatar">RD</div>
      <div><strong>Rental Desk</strong><small>Customers &amp; Reservations</small></div>
    </div>
    <div class="nav-label">Main</div>
    <nav class="side-nav">
      <a<?= $active($script === 'dashboard.php') ?> href="<?= $base ?>/dashboard.php"><span>&#9638;</span> Dashboard</a>
      <a<?= $active($section === 'customers') ?> href="<?= $base ?>/modules/customers/index.php"><span>&#128101;</span> Customers</a>
      <a<?= $active($section === 'reservations' && $script !== 'search.php') ?> href="<?= $base ?>/modules/reservations/index.php"><span>&#128197;</span> Reservations</a>
      <a<?= $active($script === 'search.php') ?> href="<?= $base ?>/modules/reservations/search.php"><span>&#128269;</span> Find vehicles</a>
      <a<?= $active($section === 'notifications') ?> href="<?= $base ?>/modules/notifications/index.php"><span>&#128276;</span> Notifications
        <?php if ($unread > 0): ?><em class="count"><?= $unread ?></em><?php endif; ?></a>
    </nav>
  </aside>
  <div class="sidebar-backdrop" id="backdrop"></div>

  <div class="content">
    <div class="topbar">
      <button class="menu-btn" type="button" id="menuBtn" aria-label="Menu">&#9776;</button>
      <form class="top-search" method="get" action="<?= $base ?>/modules/customers/index.php">
        <input name="q" placeholder="Search customers by name, phone, code..." aria-label="Search customers">
      </form>
      <a class="bell" href="<?= $base ?>/modules/notifications/index.php" aria-label="Notifications">&#128276;
        <?php if ($unread > 0): ?><em class="count"><?= $unread ?></em><?php endif; ?></a>
    </div>
    <main class="page">

<?php
// modules/nav.php  (Member 5: Eugenie)
// Shared AdminLTE 4 layout: dark sidebar + top bar + content area.
// Usage on a page:   layout_head('Page title');  ...content...  layout_foot();
// Replace with the team's main layout when Elias's version exists.

function layout_unread_count(): int {
    global $pdo;
    try {
        if (!isset($pdo)) return 0;
        return (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE channel = 'in_app' AND status <> 'read'")->fetchColumn();
    } catch (Throwable $ex) {
        return 0;
    }
}

function layout_head(string $title, ?string $pageTitle = null): void {
    $h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

    // /xampp/car/modules/maintenance/list.php -> root=/xampp/car/modules, mod=maintenance, file=list.php
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $mod    = basename(dirname($script));
    $file   = basename($script);
    $root   = rtrim(dirname(dirname($script)), '/');

    $canOps   = has_permission('reports.operational');
    $canFin   = has_permission('reports.financial');
    $canMaint = has_permission('maintenance.manage');
    $canNotif = has_permission('notifications.manage');

    $userName = (string)($_SESSION['user_name'] ?? 'Staff user');
    $initials = strtoupper(substr($userName, 0, 1));
    $unread   = $canNotif ? layout_unread_count() : 0;

    $active = function (bool $cond) { return $cond ? ' active' : ''; };
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($title) ?> | CRMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.10.0/dist/css/adminlte.min.css">
  <style>
    .small-box .small-box-icon-i { position: absolute; top: 12px; right: 15px; font-size: 68px; line-height: 1;
                                   color: rgba(0,0,0,.18); transition: transform .3s; z-index: 0; }
    .small-box:hover .small-box-icon-i { transform: scale(1.1); }
    .small-box .inner { position: relative; z-index: 1; }
    .avatar { width: 2rem; height: 2rem; border-radius: 50%; background: #0d6efd; color: #fff;
              display: inline-flex; align-items: center; justify-content: center; font-size: .85rem; font-weight: 600; }
    .chart-box { position: relative; height: 280px; }
    .empty { color: #8b93a1; text-align: center; padding: 1.75rem 1rem; }
    .empty i { font-size: 1.8rem; display: block; margin-bottom: .25rem; }
    @media print {
      .app-sidebar, .app-header, .app-footer { display: none !important; }
      .app-wrapper { display: block !important; }
      .app-main { margin: 0 !important; }
    }
  </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

  <!-- Top bar -->
  <nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a></li>
        <?php if ($canOps): ?>
          <li class="nav-item d-none d-md-block"><a href="<?= $h($root) ?>/dashboard/index.php" class="nav-link">Home</a></li>
        <?php endif; ?>
        <?php if ($canNotif): ?>
          <li class="nav-item d-none d-md-block"><a href="<?= $h($root) ?>/notifications/list.php" class="nav-link">Notifications</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php if ($canNotif): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= $h($root) ?>/notifications/list.php" title="Unread notifications">
            <i class="bi bi-bell-fill"></i>
            <?php if ($unread > 0): ?><span class="navbar-badge badge text-bg-danger"><?= (int)$unread ?></span><?php endif; ?>
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item dropdown user-menu">
          <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
            <span class="avatar me-1"><?= $h($initials) ?></span>
            <span class="d-none d-md-inline"><?= $h($userName) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
            <li class="p-3 text-center">
              <span class="avatar" style="width:3.5rem;height:3.5rem;font-size:1.4rem"><?= $h($initials) ?></span>
              <p class="mt-2 mb-0 fw-semibold"><?= $h($userName) ?></p>
              <small class="text-muted">Car Rental Management System</small>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Sidebar -->
  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
      <a href="<?= $h($root) ?>/dashboard/index.php" class="brand-link">
        <i class="bi bi-car-front-fill fs-4 mx-2"></i>
        <span class="brand-text fw-light">Car Rental <b>CRMS</b></span>
      </a>
    </div>
    <div class="sidebar-wrapper">
      <nav class="mt-2">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">

          <?php if ($canOps): ?>
          <li class="nav-item">
            <a href="<?= $h($root) ?>/dashboard/index.php" class="nav-link<?= $active($mod === 'dashboard') ?>">
              <i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if ($canMaint): ?>
          <li class="nav-item<?= $mod === 'maintenance' ? ' menu-open' : '' ?>">
            <a href="#" class="nav-link<?= $active($mod === 'maintenance') ?>">
              <i class="nav-icon bi bi-tools"></i>
              <p>Maintenance <i class="nav-arrow bi bi-chevron-right"></i></p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item"><a href="<?= $h($root) ?>/maintenance/list.php" class="nav-link<?= $active($mod === 'maintenance' && $file !== 'add.php') ?>">
                <i class="nav-icon bi bi-circle"></i><p>All maintenance</p></a></li>
              <li class="nav-item"><a href="<?= $h($root) ?>/maintenance/add.php" class="nav-link<?= $active($mod === 'maintenance' && $file === 'add.php') ?>">
                <i class="nav-icon bi bi-circle"></i><p>New maintenance</p></a></li>
            </ul>
          </li>
          <?php endif; ?>

          <?php if ($canNotif): ?>
          <li class="nav-item">
            <a href="<?= $h($root) ?>/notifications/list.php" class="nav-link<?= $active($mod === 'notifications') ?>">
              <i class="nav-icon bi bi-bell"></i>
              <p>Notifications
                <?php if ($unread > 0): ?><span class="nav-badge badge text-bg-danger me-3"><?= (int)$unread ?></span><?php endif; ?>
              </p>
            </a>
          </li>
          <?php endif; ?>

          <?php if ($canOps || $canFin): ?>
          <li class="nav-header">REPORTS</li>
          <li class="nav-item<?= $mod === 'reports' ? ' menu-open' : '' ?>">
            <a href="#" class="nav-link<?= $active($mod === 'reports') ?>">
              <i class="nav-icon bi bi-bar-chart-line"></i>
              <p>Reports <i class="nav-arrow bi bi-chevron-right"></i></p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item"><a href="<?= $h($root) ?>/reports/index.php" class="nav-link<?= $active($mod === 'reports' && $file === 'index.php') ?>">
                <i class="nav-icon bi bi-circle"></i><p>All reports</p></a></li>
              <?php
              $reportLinks = [
                  ['reservations.php', 'Reservations', $canOps],
                  ['revenue.php',      'Revenue',      $canFin],
                  ['payments.php',     'Payments',     $canFin],
                  ['utilization.php',  'Utilization',  $canOps],
                  ['maintenance.php',  'Maintenance',  $canOps],
              ];
              foreach ($reportLinks as [$f, $label, $ok]):
                  if (!$ok) continue; ?>
                <li class="nav-item"><a href="<?= $h($root) ?>/reports/<?= $h($f) ?>" class="nav-link<?= $active($mod === 'reports' && $file === $f) ?>">
                  <i class="nav-icon bi bi-circle"></i><p><?= $h($label) ?></p></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
          <?php endif; ?>

        </ul>
      </nav>
    </div>
  </aside>

  <!-- Main content -->
  <main class="app-main">
    <div class="app-content-header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-sm-6"><?php if ($pageTitle): ?><h3 class="mb-0"><?= $h($pageTitle) ?></h3><?php endif; ?></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end mb-0">
              <li class="breadcrumb-item"><a href="<?= $h($root) ?>/dashboard/index.php">Home</a></li>
              <li class="breadcrumb-item active" aria-current="page"><?= $h($title) ?></li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <div class="app-content">
      <div class="container-fluid">
<?php
}

/** Closes the layout. $extraScripts = trusted <script> blocks to load after AdminLTE. */
function layout_foot(string $extraScripts = ''): void {
    ?>
      </div>
    </div>
  </main>

  <footer class="app-footer">
    <div class="float-end d-none d-sm-inline">Car Rental Management System</div>
    <strong>CRMS</strong> &copy; <?= date('Y') ?>. Layout by AdminLTE 4.
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.10.0/dist/js/adminlte.min.js"></script>
<?= $extraScripts ?>
</body>
</html>
<?php
}
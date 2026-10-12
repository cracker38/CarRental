<?php
// Works wherever the project folder sits under htdocs
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$root    = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$base    = substr($root, strlen($docRoot));          // e.g. /xampp/car/CarRental
$pageTitle = $pageTitle ?? 'CRMS';
$section = basename(dirname($_SERVER['SCRIPT_FILENAME']));
$script  = basename($_SERVER['SCRIPT_FILENAME']);
$active = fn(bool $on) => $on ? ' class="active"' : '';
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
<header class="site-header">
  <div class="container nav-bar">
    <a class="brand" href="<?= $base ?>/dashboard.php">&#128663; CRMS</a>
    <button class="nav-toggle" type="button" aria-label="Menu" aria-expanded="false">&#9776;</button>
    <nav class="main-nav" id="main-nav">
      <a<?= $active($script === 'dashboard.php') ?> href="<?= $base ?>/dashboard.php">Dashboard</a>
      <a<?= $active($section === 'customers') ?> href="<?= $base ?>/modules/customers/index.php">Customers</a>
      <a<?= $active($section === 'reservations' && $script !== 'search.php') ?> href="<?= $base ?>/modules/reservations/index.php">Reservations</a>
      <a<?= $active($script === 'search.php') ?> href="<?= $base ?>/modules/reservations/search.php">Find vehicles</a>
      <a<?= $active($section === 'notifications') ?> href="<?= $base ?>/modules/notifications/index.php">Notifications</a>
    </nav>
  </div>
</header>
<main class="container">
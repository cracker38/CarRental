<?php
declare(strict_types=1);
$appName = (string) setting('app_name', 'CRMS');
$businessName = (string) setting('business_name', 'Car Rental');
$pageTitle = $pageTitle ?? 'Sign in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-grid">
    <section class="auth-hero">
        <div>
            <p class="eyebrow">Car Rental Management System</p>
            <h1><?= e($businessName) ?></h1>
            <p>Secure operations for fleet, reservations, rentals, payments, and administration.</p>
        </div>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <div class="mb-4">
                <strong class="fs-5"><?= e($appName) ?></strong>
                <div class="text-secondary"><?= e($pageTitle) ?></div>
            </div>
            <?php require BASE_PATH . '/includes/flash.php'; ?>
            <?= $authContent ?? '' ?>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

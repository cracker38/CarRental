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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-grid">
    <section class="auth-panel">
        <div class="auth-card">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="brand-mark" style="width:38px;height:38px;font-size:18px;"><i class="bi bi-car-front-fill"></i></span>
                <div>
                    <strong><?= e($appName) ?></strong>
                    <div class="text-secondary small"><?= e($pageTitle) ?></div>
                </div>
            </div>
            <?php require BASE_PATH . '/includes/flash.php'; ?>
            <?= $authContent ?? '' ?>
        </div>
    </section>
    <section class="auth-hero">
        <div>
            <p class="eyebrow">Car Rental Management System</p>
            <h1><?= e($businessName) ?></h1>
            <p>Secure staff access for fleet, reservations, rentals, payments, and administration — built for day-to-day operations.</p>
            <div class="hero-points">
                <span><i class="bi bi-shield-check"></i> Role-based access control</span>
                <span><i class="bi bi-clock-history"></i> Full audit trail</span>
                <span><i class="bi bi-geo-alt"></i> <?= e((string) setting('business_country', 'Rwanda')) ?> · <?= e((string) setting('default_currency', 'RWF')) ?></span>
            </div>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>

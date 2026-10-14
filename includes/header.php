<?php
declare(strict_types=1);

/**
 * Shared CarRental page header.
 * Expects app/bootstrap.php to have been loaded before this file.
 */
$pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== ''
    ? $pageTitle
    : 'Dashboard';
$displayName = '';
if (isset($user) && is_array($user)) {
    $displayName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
}
$roleName = isset($user) && is_array($user) && isset($user['role_name']) && is_string($user['role_name'])
    ? trim($user['role_name'])
    : '';
if ($displayName === '' && isset($_SESSION['user_name']) && is_string($_SESSION['user_name'])) {
    $displayName = $_SESSION['user_name'];
}
if ($displayName === '') {
    $displayName = 'Staff member';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#172b5c">
    <title><?= e($pageTitle) ?> | CarRental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --cr-ink:#172033; --cr-muted:#6b7280; --cr-line:#e6eaf1; --cr-blue:#2457d6; --cr-navy:#152858; }
        body { margin:0; color:var(--cr-ink); background:#f4f7fb; font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif; }
        .cr-topbar { position:sticky; top:0; z-index:1000; background:rgba(255,255,255,.96); backdrop-filter:blur(14px); border-bottom:1px solid var(--cr-line); }
        .cr-topbar-inner { min-height:72px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
        .cr-brand { display:flex; align-items:center; gap:11px; color:var(--cr-ink); text-decoration:none; font-weight:850; letter-spacing:-.04em; font-size:20px; }
        .cr-brand-mark { display:grid; place-items:center; width:40px; height:40px; border-radius:12px; color:white; background:linear-gradient(145deg,#3776f6,#183c9d); box-shadow:0 7px 17px #2457d62b; font-size:14px; letter-spacing:-.03em; }
        .cr-brand small { display:block; margin-top:-3px; color:var(--cr-muted); font-size:10px; font-weight:650; letter-spacing:.11em; text-transform:uppercase; }
        .cr-avatar { display:grid; place-items:center; width:32px; height:32px; border-radius:50%; color:#2457d6; background:#eaf0ff; font-weight:800; }
        .cr-profile-button { display:flex; align-items:center; gap:10px; padding:6px 12px 6px 6px; border:1px solid var(--cr-line); border-radius:999px; background:#fff; color:var(--cr-ink); font:inherit; font-size:13px; font-weight:700; }
        .cr-profile-button::after { margin-left:2px; color:#758198; }
        .cr-profile-button:hover,.cr-profile-button:focus-visible { border-color:#cad5ec; background:#fbfcff; }
        .cr-profile-menu { min-width:220px; margin-top:9px!important; padding:7px; border-color:var(--cr-line); border-radius:14px; box-shadow:0 16px 38px rgba(24,39,75,.13); }
        .cr-profile-summary { display:flex; flex-direction:column; gap:4px; padding:10px 11px 8px; }
        .cr-profile-summary strong { font-size:13px; }
        .cr-profile-summary small { color:var(--cr-muted); font-size:11px; }
        .cr-profile-menu .dropdown-divider { margin:5px 0; }
        .cr-signout { display:flex; align-items:center; gap:9px; border-radius:8px; padding:9px 11px; color:#9b3440; font-size:12px; font-weight:700; }
        .cr-signout:hover,.cr-signout:focus { background:#fff2f2; color:#842b37; }
        .cr-page-wrap { min-height:calc(100vh - 130px); }
        .cr-footer { padding:22px 0; color:#778195; font-size:12px; }
        @media(max-width:575px) { .cr-topbar-inner { min-height:64px; } .cr-brand { font-size:17px; } .cr-user-label { display:none; } .cr-profile-button { padding-right:7px; } }
    </style>
    <?php if (function_exists('ro_styles')) { ro_styles(); } ?>
</head>
<body>
<header class="cr-topbar">
    <div class="container-fluid px-3 px-lg-4">
        <div class="cr-topbar-inner">
            <a class="cr-brand" href="<?= e(base_url('admin/rental-operations.php')) ?>">
                <span class="cr-brand-mark">CR</span>
                <span>CarRental<small>Management workspace</small></span>
            </a>
            <?php if (is_logged_in()): ?>
                <div class="dropdown">
                    <button class="cr-profile-button dropdown-toggle" type="button" id="cr-profile-menu" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="cr-avatar"><?= e(strtoupper(substr($displayName, 0, 1))) ?></span>
                        <span class="cr-user-label"><?= e($displayName) ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end cr-profile-menu" aria-labelledby="cr-profile-menu">
                        <div class="cr-profile-summary">
                            <strong><?= e($displayName) ?></strong>
                            <?php if ($roleName !== ''): ?><small><?= e($roleName) ?></small><?php endif; ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <form method="post" action="<?= e(base_url('logout.php')) ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item cr-signout" type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="cr-page-wrap">

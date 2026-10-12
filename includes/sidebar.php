<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-car"></i>
        <span>Car Rental</span>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>/index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <?php if (hasPermission('vehicles.view') || hasPermission('vehicles.manage')): ?>
        <a href="<?= BASE_URL ?>/vehicles/index.php" class="nav-item <?= str_contains($_SERVER['PHP_SELF'], '/vehicles/') && basename($_SERVER['PHP_SELF']) !== 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-car-side"></i> <span>Vehicles</span>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('vehicles.manage')): ?>
        <a href="<?= BASE_URL ?>/vehicles/categories.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> <span>Categories</span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

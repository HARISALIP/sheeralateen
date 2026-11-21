<?php
/**
 * manager/includes/sidebar.php
 * Fixed navigation sidebar.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <span class="brand-short" style="display:none; font-weight:800; font-size:18px; color:var(--primary);">SL</span>
            <span class="brand-full"><span>S</span>heera Lateen</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" title="Dashboard">
            <i class="fa-solid fa-house"></i> <span>Dashboard</span>
        </a>
        <a href="inventory.php" class="nav-item <?= $currentPage === 'inventory.php' ? 'active' : '' ?>" title="Inventory">
            <i class="fa-solid fa-boxes-stacked"></i> <span>Inventory</span>
        </a>
        <a href="orders.php" class="nav-item <?= $currentPage === 'orders.php' ? 'active' : '' ?>" title="Orders">
            <i class="fa-solid fa-box"></i> <span>Orders</span>
        </a>
        <a href="profile.php" class="nav-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>" title="My Profile">
            <i class="fa-solid fa-user"></i> <span>My Profile</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-logout" title="Logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </div>
</aside>


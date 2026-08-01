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
            <span>S</span>heera Lateen
        </a>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>
        <a href="orders.php" class="nav-item <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-box"></i> Orders
        </a>
        <a href="profile.php" class="nav-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-user"></i> My Profile
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>


<?php
/**
 * admin/includes/sidebar.php
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
        <a href="orders.php" class="nav-item <?= $currentPage === 'orders.php' ? 'active' : '' ?>" title="Orders">
            <i class="fa-solid fa-box"></i> <span>Orders</span>
        </a>
        <a href="inventory.php" class="nav-item <?= $currentPage === 'inventory.php' ? 'active' : '' ?>" title="Inventory">
            <i class="fa-solid fa-boxes-stacked"></i> <span>Inventory</span>
        </a>
        <a href="branches.php" class="nav-item <?= $currentPage === 'branches.php' ? 'active' : '' ?>" title="Branches">
            <i class="fa-solid fa-store"></i> <span>Branches</span>
        </a>
        <a href="managers.php" class="nav-item <?= $currentPage === 'managers.php' ? 'active' : '' ?>" title="Managers">
            <i class="fa-solid fa-users"></i> <span>Managers</span>
        </a>
        <a href="reports.php" class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>" title="Reports">
            <i class="fa-solid fa-chart-line"></i> <span>Reports</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-logout" title="Logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </div>
</aside>


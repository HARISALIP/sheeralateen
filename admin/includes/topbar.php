<?php
/**
 * admin/includes/topbar.php
 * Horizontal top navigation bar — polished v2.
 */
?>
<div class="main-wrapper">
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <div class="topbar-right">
            <?php if(false): ?>
            <button class="topbar-icon" title="Notifications" id="btn-notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="badge">3</span>
            </button>
            <?php endif; ?>

            <a href="profile.php" class="user-profile" id="link-profile">
                <div class="avatar">
                    <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></span>
                    <span class="user-role">Super Admin</span>
                </div>
            </a>
        </div>
    </header>

    <main class="content">

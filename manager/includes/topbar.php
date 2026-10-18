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
            <button class="topbar-icon" title="Order Alerts & Sound" id="btn-notifications" type="button" style="background: none; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-color, #1e293b); margin-right: 12px;">
                <i class="fa-solid fa-bell"></i>
            </button>

            <a href="profile.php" class="user-profile" id="link-profile">
                <div class="avatar">
                    <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Manager') ?></span>
                    <span class="user-role">Branch Manager</span>
                </div>
            </a>
        </div>
    </header>

    <main class="content">

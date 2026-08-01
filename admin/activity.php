<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$pageTitle = 'Activity Log';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Activity Log</h1>
        <p class="page-subtitle">Review system events and user actions.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p style="color: var(--text-muted);">Activity log viewer will be implemented in the next phase.</p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

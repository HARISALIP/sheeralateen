<?php
/**
 * admin/dashboard.php
 * Enhanced Dashboard with live database data:
 *  - Real-time stat cards (orders, branches, managers)
 *  - Recent Orders widget
 *  - Recent Activity log widget
 *  - Recently Added Managers widget
 *  - Recently Added Branches widget
 *  - System status indicators
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

/* ──────────────────────────────────────────────────────────
   LIVE STAT QUERIES
────────────────────────────────────────────────────────── */

// Orders
$totalOrders     = (int) $db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL")->fetchColumn();
$pendingOrders   = (int) $db->query("SELECT COUNT(*) FROM orders WHERE current_status IN ('New','Assigned','Accepted','Preparing','Ready','Out For Delivery') AND deleted_at IS NULL")->fetchColumn();
$deliveredOrders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE current_status = 'Delivered' AND deleted_at IS NULL")->fetchColumn();

// Branches
$totalBranches  = (int) $db->query("SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL")->fetchColumn();
$activeBranches = (int) $db->query("SELECT COUNT(*) FROM branches WHERE status='active' AND deleted_at IS NULL")->fetchColumn();

// Managers
$totalManagers  = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='branch_manager' AND deleted_at IS NULL")->fetchColumn();
$activeManagers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='branch_manager' AND status='active' AND deleted_at IS NULL")->fetchColumn();

/* ──────────────────────────────────────────────────────────
   RECENT ORDERS (last 6)
────────────────────────────────────────────────────────── */
$recentOrders = $db->query("
    SELECT o.order_number, o.customer_name, o.current_status,
           o.total_amount, o.created_at, b.branch_name
    FROM orders o
    LEFT JOIN branches b ON o.assigned_branch_id = b.id AND b.deleted_at IS NULL
    WHERE o.deleted_at IS NULL
    ORDER BY o.created_at DESC
    LIMIT 6
")->fetchAll();

/* ──────────────────────────────────────────────────────────
   SHOPIFY SYNC STATS
────────────────────────────────────────────────────────── */
$queue = new SyncQueue($db);
$syncStats = $queue->getStats();
$lastSyncStatus = get_setting($db, 'shopify_last_sync_status', 'Not Synced');
$lastSyncTime = get_setting($db, 'shopify_last_sync_time', 'Never');
$lastFailedTime = get_setting($db, 'shopify_last_failed_sync', 'Never');
$shopifyApiVersion = get_setting($db, 'shopify_api_version', '2026-04');
$syncInterval = get_setting($db, 'sync_interval_minutes', '5');

/* ──────────────────────────────────────────────────────────
   RECENT ACTIVITY (last 10 from activity_logs)
────────────────────────────────────────────────────────── */
$recentActivity = $db->query("
    SELECT al.action, al.description, al.created_at, u.name AS user_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();

/* ──────────────────────────────────────────────────────────
   RECENTLY ADDED MANAGERS (last 5)
────────────────────────────────────────────────────────── */
$recentManagers = $db->query("
    SELECT u.name, u.email, u.status, u.created_at, b.branch_name
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id AND b.deleted_at IS NULL
    WHERE u.role = 'branch_manager' AND u.deleted_at IS NULL
    ORDER BY u.created_at DESC
    LIMIT 5
")->fetchAll();

/* ──────────────────────────────────────────────────────────
   RECENTLY ADDED BRANCHES (last 5)
────────────────────────────────────────────────────────── */
$recentBranches = $db->query("
    SELECT b.branch_name, b.branch_code, b.status, b.created_at, u.name AS manager_name
    FROM branches b
    LEFT JOIN users u ON b.branch_manager_id = u.id AND u.deleted_at IS NULL
    WHERE b.deleted_at IS NULL
    ORDER BY b.created_at DESC
    LIMIT 5
")->fetchAll();

/* ──────────────────────────────────────────────────────────
   STATUS COLOUR MAP  (for order status badges)
────────────────────────────────────────────────────────── */
function orderStatusClass(string $s): string {
    return match ($s) {
        'New'             => 'pending',
        'Assigned',
        'Accepted'        => 'processing',
        'Preparing',
        'Ready',
        'Out For Delivery'=> 'processing',
        'Delivered'       => 'completed',
        'Cancelled',
        'Returned'        => 'inactive',
        default           => 'pending',
    };
}

/* ──────────────────────────────────────────────────────────
   ACTION ICON MAP  (for activity log)
────────────────────────────────────────────────────────── */
function activityIcon(string $action): string {
    return match (true) {
        str_contains($action, 'login')   => 'fa-right-to-bracket',
        str_contains($action, 'logout')  => 'fa-right-from-bracket',
        str_contains($action, 'branch')  => 'fa-store',
        str_contains($action, 'manager') => 'fa-user',
        str_contains($action, 'order')   => 'fa-box',
        str_contains($action, 'setting') => 'fa-gear',
        str_contains($action, 'password')=> 'fa-key',
        default => 'fa-circle-dot',
    };
}

function activityDotColor(string $action): string {
    return match (true) {
        str_contains($action, 'deleted') || str_contains($action, 'cancel') => '#ef4444',
        str_contains($action, 'created')                                    => '#10b981',
        str_contains($action, 'updated') || str_contains($action, 'reset')  => '#f59e0b',
        str_contains($action, 'deactivated')                                => '#64748b',
        default => '#4f46e5',
    };
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Overview</h1>
        <p class="page-subtitle">
            Welcome back, <?= e($_SESSION['name']) ?>.
            Here is what&rsquo;s happening across your branches.
        </p>
    </div>
    <div class="page-actions">
        <a href="branches.php" class="btn btn-outline" id="btn-manage-branches">
            <i class="fa-solid fa-store"></i> Manage Branches
        </a>
        <a href="managers.php" class="btn btn-primary" id="btn-add-manager">
            <i class="fa-solid fa-user-plus"></i> Add Manager
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     STAT CARDS — ROW 1  (Orders)
════════════════════════════════════════════════════ -->
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fa-solid fa-box-open"></i></div>
        <div class="stat-details">
            <h3><?= number_format($totalOrders) ?></h3>
            <p>Total Orders</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-details">
            <h3><?= number_format($pendingOrders) ?></h3>
            <p>Active Orders</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fa-solid fa-check-circle"></i></div>
        <div class="stat-details">
            <h3><?= number_format($deliveredOrders) ?></h3>
            <p>Delivered</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fa-solid fa-store"></i></div>
        <div class="stat-details">
            <h3><?= $activeBranches ?> / <?= $totalBranches ?></h3>
            <p>Active Branches</p>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     STAT CARDS — ROW 2  (People)
════════════════════════════════════════════════════ -->
<div class="dashboard-grid dashboard-grid-2">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-light);color:var(--primary);">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-details">
            <h3><?= $activeManagers ?> / <?= $totalManagers ?></h3>
            <p>Active Managers</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="stat-details">
            <h3><a href="reports.php">View Reports &rsaquo;</a></h3>
            <p>Analytics &amp; Reports</p>
        </div>
    </div>
</div>

<?php if (false): // Hidden for now as requested ?>
<!-- ═══════════════════════════════════════════════════
     SHOPIFY SYNC WIDGET
════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title"><i class="fa-brands fa-shopify" style="color:#95bf47;"></i> Shopify Integration</h2>
        <div>
            <a href="settings.php?tab=shopify" class="btn btn-sm btn-outline">Settings</a>
            <a href="orders.php" class="btn btn-sm btn-primary" style="margin-left: 8px;">View Orders</a>
        </div>
    </div>
    <div class="card-body" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div>
            <strong class="text-muted">Connection Status</strong><br>
            <?php if ($lastSyncStatus === 'Healthy'): ?>
                <span class="status completed"><i class="fa-solid fa-circle-check"></i> Healthy</span>
            <?php elseif ($lastSyncStatus === 'Warning'): ?>
                <span class="status processing"><i class="fa-solid fa-circle-exclamation"></i> Warning</span>
            <?php elseif ($lastSyncStatus === 'Error'): ?>
                <span class="status inactive"><i class="fa-solid fa-triangle-exclamation"></i> Error</span>
            <?php else: ?>
                <span class="status pending">Not Synced</span>
            <?php endif; ?>
        </div>
        <div>
            <strong class="text-muted">API Version & Interval</strong><br>
            <span style="font-size: 15px; font-weight: 600;"><?= e($shopifyApiVersion) ?></span> <span class="text-muted">(@<?= e($syncInterval) ?>m)</span>
        </div>
        <div>
            <strong class="text-muted">Queue Status</strong><br>
            <span style="font-size: 15px; font-weight: 600;"><?= $syncStats['pending'] ?></span> pending /
            <span style="color:var(--danger); font-weight: 600;"><?= $syncStats['failed'] ?></span> failed
        </div>
        <div>
            <strong class="text-muted">Last Successful Sync</strong><br>
            <span style="font-size: 14px;"><?= e($lastSyncTime) ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════
     MAIN SECTIONS: Recent Orders + Activity
════════════════════════════════════════════════════ -->
<div class="dashboard-sections">

    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Orders</h2>
            <a href="orders.php" class="btn btn-sm btn-outline" id="link-view-all-orders">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table" id="recent-orders-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Branch</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fa-solid fa-box"></i>
                            No orders yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><strong><?= e($order['order_number']) ?></strong></td>
                        <td><?= $order['branch_name'] ? e($order['branch_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                        <td><?= e($order['customer_name']) ?></td>
                        <td>
                            <span class="status <?= orderStatusClass($order['current_status']) ?>">
                                <?= e($order['current_status']) ?>
                            </span>
                        </td>
                        <td><?= number_format($order['total_amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Activity</h2>
            <a href="activity.php" class="btn btn-sm btn-outline" id="link-view-all-activity">View All</a>
        </div>
        <div class="card-body" style="padding:16px 24px;">
            <?php if (empty($recentActivity)): ?>
            <p class="text-muted" style="text-align:center;padding:20px 0;font-size:13px;">No activity recorded yet.</p>
            <?php else: ?>
            <ul class="recent-activity-list" id="activity-list">
                <?php foreach ($recentActivity as $log): ?>
                <li class="recent-activity-item">
                    <div class="activity-dot" style="background:<?= activityDotColor($log['action']) ?>;"></div>
                    <div class="meta">
                        <strong>
                            <?= $log['user_name'] ? e($log['user_name']) : 'System' ?>
                            &mdash;
                            <span style="font-weight:400;color:var(--text-muted);">
                                <?= e(ucwords(str_replace('_', ' ', $log['action']))) ?>
                            </span>
                        </strong>
                        <?php if ($log['description']): ?>
                        <span style="display:block;margin-top:2px;font-size:12px;color:var(--text-main);opacity:0.8;">
                            <?= e($log['description']) ?>
                        </span>
                        <?php endif; ?>
                        <span><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php if (false): // Hidden for now as requested ?>
<!-- ═══════════════════════════════════════════════════
     BOTTOM GRID: Managers + Branches + System Status
════════════════════════════════════════════════════ -->
<div class="dashboard-bottom">

    <!-- Recently Added Managers -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Managers</h2>
            <a href="managers.php" class="btn btn-sm btn-outline" id="link-all-managers">All Managers</a>
        </div>
        <div class="card-body" style="padding:16px 24px;">
            <?php if (empty($recentManagers)): ?>
            <p class="text-muted" style="text-align:center;padding:16px 0;font-size:13px;">No managers added yet.</p>
            <?php else: ?>
            <ul class="mini-list" id="recent-managers-list">
                <?php foreach ($recentManagers as $mgr): ?>
                <li class="mini-list-item">
                    <div class="mini-avatar"><?= strtoupper(substr($mgr['name'], 0, 1)) ?></div>
                    <div class="item-meta">
                        <strong><?= e($mgr['name']) ?></strong>
                        <span>
                            <?= $mgr['branch_name'] ? e($mgr['branch_name']) : 'Unassigned' ?>
                            &bull;
                            <?= date('d M Y', strtotime($mgr['created_at'])) ?>
                        </span>
                    </div>
                    <span class="status <?= e($mgr['status']) ?>" style="font-size:11px;">
                        <?= ucfirst($mgr['status']) ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recently Added Branches -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Branches</h2>
            <a href="branches.php" class="btn btn-sm btn-outline" id="link-all-branches">All Branches</a>
        </div>
        <div class="card-body" style="padding:16px 24px;">
            <?php if (empty($recentBranches)): ?>
            <p class="text-muted" style="text-align:center;padding:16px 0;font-size:13px;">No branches added yet.</p>
            <?php else: ?>
            <ul class="mini-list" id="recent-branches-list">
                <?php foreach ($recentBranches as $branch): ?>
                <li class="mini-list-item">
                    <div class="mini-avatar branch-av">
                        <i class="fa-solid fa-store" style="font-size:14px;"></i>
                    </div>
                    <div class="item-meta">
                        <strong><?= e($branch['branch_name']) ?></strong>
                        <span>
                            <?= e($branch['branch_code']) ?>
                            <?php if ($branch['manager_name']): ?>
                            &bull; <?= e($branch['manager_name']) ?>
                            <?php endif; ?>
                            &bull; <?= date('d M Y', strtotime($branch['created_at'])) ?>
                        </span>
                    </div>
                    <span class="status <?= e($branch['status']) ?>" style="font-size:11px;">
                        <?= ucfirst($branch['status']) ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Status -->
    <div>
        <!-- Quick Actions -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="branches.php" class="quick-action-btn" id="qa-branches">
                        <i class="fa-solid fa-store"></i>
                        Branches
                    </a>
                    <a href="managers.php" class="quick-action-btn" id="qa-managers">
                        <i class="fa-solid fa-user-plus"></i>
                        Managers
                    </a>
                    <a href="reports.php" class="quick-action-btn" id="qa-reports">
                        <i class="fa-solid fa-chart-line"></i>
                        Reports
                    </a>
                    <a href="settings.php" class="quick-action-btn" id="qa-settings">
                        <i class="fa-solid fa-gear"></i>
                        Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">System Status</h2>
            </div>
            <div class="card-body">
                <div class="system-status" id="system-status">
                    <?php
                    // Live DB check
                    try {
                        $db->query("SELECT 1");
                        $dbOk = true;
                    } catch (Exception $e) {
                        $dbOk = false;
                    }
                    $shopifyUrl = get_setting($db, 'shopify_store_url');
                    ?>
                    <div class="status-item">
                        <div class="status-label">
                            <div class="status-dot <?= $dbOk ? '' : 'error' ?>"></div>
                            Database
                        </div>
                        <span class="status-val"><?= $dbOk ? 'Connected' : 'Error' ?></span>
                    </div>
                    <div class="status-item">
                        <div class="status-label">
                            <div class="status-dot <?= $shopifyUrl ? '' : 'warning' ?>"></div>
                            Shopify
                        </div>
                        <span class="status-val"><?= $shopifyUrl ? 'URL configured' : 'Not configured' ?></span>
                    </div>
                    <div class="status-item">
                        <div class="status-label">
                            <div class="status-dot"></div>
                            Active Branches
                        </div>
                        <span class="status-val"><?= $activeBranches ?> / <?= $totalBranches ?></span>
                    </div>
                    <div class="status-item">
                        <div class="status-label">
                            <div class="status-dot"></div>
                            Active Managers
                        </div>
                        <span class="status-val"><?= $activeManagers ?> / <?= $totalManagers ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

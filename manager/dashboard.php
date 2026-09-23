<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('branch_manager', '../login.php');

$db = Database::getConnection();
$branchId = (int) $_SESSION['branch_id'];

if (!$branchId) {
    die('<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access Denied</title><style>body{font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f7fa;text-align:center;} .box{background:#fff;padding:40px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.05);max-width:400px;width:90%;} h2{margin-top:0;color:#1e293b;} p{color:#475569;margin-bottom:24px;line-height:1.5;} .btn{display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;transition:0.2s;} .btn:hover{background:#4338ca;}</style></head><body><div class="box"><h2>Access Denied</h2><p>Error: No branch has been assigned to your manager account yet.<br><br>Please contact the Super Admin to assign you to a branch.</p><a href="logout.php" class="btn">Log Out & Return</a></div></body></html>');
}

/* ──────────────────────────────────────────────────────────
   LIVE STAT QUERIES FOR MANAGER'S BRANCH
────────────────────────────────────────────────────────── */
// Orders
$totalOrders     = (int) $db->query("SELECT COUNT(*) FROM orders WHERE assigned_branch_id = $branchId AND deleted_at IS NULL")->fetchColumn();
$pendingOrders   = (int) $db->query("SELECT COUNT(*) FROM orders WHERE assigned_branch_id = $branchId AND current_status IN ('New','Assigned','Accepted','Preparing','Ready','Out For Delivery') AND deleted_at IS NULL")->fetchColumn();
$deliveredOrders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE assigned_branch_id = $branchId AND current_status = 'Delivered' AND deleted_at IS NULL")->fetchColumn();

/* ──────────────────────────────────────────────────────────
   RECENT ORDERS (last 10)
────────────────────────────────────────────────────────── */
$recentOrders = $db->query("
    SELECT o.id, o.order_number, o.customer_name, o.current_status,
           o.total_amount, o.created_at
    FROM orders o
    WHERE o.assigned_branch_id = $branchId AND o.deleted_at IS NULL
    ORDER BY o.created_at DESC
    LIMIT 10
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
        'Ready'           => 'processing',
        'Out For Delivery'=> 'shipped',
        'Delivered'       => 'delivered',
        'Cancelled',
        'Returned'        => 'cancelled',
        default           => 'pending'
    };
}

$pageTitle = 'Manager Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?></h1>
        <p class="page-subtitle">Here is what is happening at your branch today.</p>
    </div>
</div>

<!-- TOP STATS -->
<div class="dashboard-grid">
    <!-- Stat: Total Orders -->
    <div class="stat-card">
        <div class="stat-info">
            <h3 class="stat-title">Total Orders</h3>
            <div class="stat-value"><?= number_format($totalOrders) ?></div>
        </div>
        <div class="stat-icon" style="color: var(--primary); background: var(--primary-subtle);">
            <i class="fa-solid fa-box"></i>
        </div>
    </div>

    <!-- Stat: Pending Orders -->
    <div class="stat-card">
        <div class="stat-info">
            <h3 class="stat-title">Pending Orders</h3>
            <div class="stat-value"><?= number_format($pendingOrders) ?></div>
        </div>
        <div class="stat-icon" style="color: var(--warning); background: var(--warning-bg);">
            <i class="fa-solid fa-clock"></i>
        </div>
    </div>

    <!-- Stat: Delivered Orders -->
    <div class="stat-card">
        <div class="stat-info">
            <h3 class="stat-title">Delivered</h3>
            <div class="stat-value"><?= number_format($deliveredOrders) ?></div>
        </div>
        <div class="stat-icon" style="color: var(--success); background: var(--success-bg);">
            <i class="fa-solid fa-check"></i>
        </div>
    </div>
</div>

<div class="dashboard-sections">
    <!-- RECENT ORDERS -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Orders</h2>
            <a href="orders.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-responsive">
            <div class="table-responsive">
                <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fa-solid fa-folder-open"></i>
                            <p>No orders found for your branch.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $ro): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ro['order_number']) ?></strong></td>
                        <td><?= htmlspecialchars($ro['customer_name'] ?: 'Guest') ?></td>
                        <td>
                            <span class="status-badge <?= orderStatusClass($ro['current_status']) ?>">
                                <?= htmlspecialchars($ro['current_status']) ?>
                            </span>
                        </td>
                        <td>$<?= number_format((float)$ro['total_amount'], 2) ?></td>
                        <td class="text-muted text-sm"><?= date('M j, Y g:i A', strtotime($ro['created_at'])) ?></td>
                        <td class="text-right">
                            <a href="order_details.php?id=<?= $ro['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


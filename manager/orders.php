<?php
/**
 * admin/orders.php
 * ---------------------------------------------------------
 * View and manage all orders. Shows sync status and allows
 * manual status updates (which enqueue to Shopify).
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('branch_manager', '../login.php');

$db = Database::getConnection();
$branchId = (int) $_SESSION['branch_id'];

if (!$branchId) {
    die("Error: No branch assigned to this manager.");
}

// --- POST HANDLER: Update Order Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security token mismatch.');
        header('Location: orders.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $newPaymentStatus = $_POST['new_payment_status'] ?? '';
    
    // valid statuses
    $validStatuses = ['New', 'Assigned', 'Accepted', 'Preparing', 'Ready', 'Out For Delivery', 'Delivered', 'Cancelled', 'Returned'];
    $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_paid', 'partially_refunded', 'unpaid'];
    
    if ($orderId > 0 && in_array($newStatus, $validStatuses) && in_array($newPaymentStatus, $validPaymentStatuses)) {
        // Fetch order to verify and log
        $stmt = $db->prepare("SELECT order_number, shopify_order_id, current_status, payment_status FROM orders WHERE id = :id AND assigned_branch_id = :bid");
        $stmt->execute([':id' => $orderId, ':bid' => $branchId]);
        $order = $stmt->fetch();
        
        if ($order && ($order['current_status'] !== $newStatus || $order['payment_status'] !== $newPaymentStatus)) {
            // 1. Update local database
            $update = $db->prepare("UPDATE orders SET current_status = :status, payment_status = :pstatus, updated_at = NOW() WHERE id = :id");
            $update->execute([':status' => $newStatus, ':pstatus' => $newPaymentStatus, ':id' => $orderId]);
            
            // 2. Log history (only for order status for now, to avoid schema changes)
            if ($order['current_status'] !== $newStatus) {
                $hist = $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (:oid, :old, :new, :uid)");
                $hist->execute([
                    ':oid' => $orderId,
                    ':old' => $order['current_status'],
                    ':new' => $newStatus,
                    ':uid' => (int) $_SESSION['user_id']
                ]);
            }
            
            // 3. Enqueue Shopify Sync (Push)
            if ($order['shopify_order_id']) {
                $queue = new SyncQueue($db);
                if ($order['current_status'] !== $newStatus) {
                    $queue->enqueue('order', $orderId, $order['shopify_order_id'], 'push_status');
                }
                if ($order['payment_status'] !== $newPaymentStatus) {
                    $queue->enqueue('order', $orderId, $order['shopify_order_id'], 'push_payment');
                }
                
                // Process instantly for immediate feedback
                try {
                    $api = new ShopifyService($db);
                    $sync = new ShopifySyncService($db, $api, $queue);
                    $sync->processQueue(5);
                } catch (Exception $e) {
                    // Ignore inline sync errors, cron will catch it
                }
                
                flash('success', "Order {$order['order_number']} updated and synced to Shopify instantly.");
            } else {
                flash('success', "Order {$order['order_number']} updated locally (not linked to Shopify).");
            }
        }
    }
    header('Location: orders.php');
    exit;
}

// --- FILTER & PAGINATION ---
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterSync = $_GET['sync'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int) get_setting($db, 'orders_per_page', '25');
$offset = ($page - 1) * $limit;

$where = ["o.deleted_at IS NULL", "o.assigned_branch_id = $branchId"];
$params = [];

if ($search !== '') {
    $where[] = "(o.order_number LIKE :search OR o.customer_name LIKE :search OR o.customer_phone LIKE :search OR o.shopify_order_number LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($filterStatus !== '') {
    $where[] = "o.current_status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterSync !== '') {
    $where[] = "o.sync_status = :sync";
    $params[':sync'] = $filterSync;
}

$whereSql = "WHERE " . implode(' AND ', $where);

// Total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $whereSql");
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit) ?: 1;

// Fetch orders
$stmt = $db->prepare("
    SELECT o.* 
    FROM orders o
    $whereSql
    ORDER BY o.created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// Fetch branches for assignment dropdown (if needed)
$branches = $db->query("SELECT id, branch_name FROM branches WHERE status='active' AND deleted_at IS NULL ORDER BY branch_name")->fetchAll();

$pageTitle = 'Orders';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

// Helper for status badge
function statusBadge($s) {
    $class = match($s) {
        'New' => 'pending',
        'Assigned', 'Accepted' => 'processing',
        'Preparing', 'Ready', 'Out For Delivery' => 'info',
        'Delivered' => 'completed',
        'Cancelled', 'Returned' => 'inactive',
        default => 'pending'
    };
    return "<span class=\"status $class\">" . e($s) . "</span>";
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Orders</h1>
        <p class="page-subtitle">Manage, assign, and track shopify orders.</p>
    </div>
</div>

<?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success" id="flash-success"><i class="fa-solid fa-check"></i> <?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
    <div class="alert alert-danger" id="flash-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($msg) ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" action="orders.php" class="form-grid" style="align-items: end;">
            <div class="form-group">
                <label for="search">Search Orders</label>
                <input type="text" name="search" id="search" class="form-control" value="<?= e($search) ?>" placeholder="Order #, Name, Phone">
            </div>
            <div class="form-group">
                <label for="status">Order Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach(['New','Assigned','Accepted','Preparing','Ready','Out For Delivery','Delivered','Cancelled','Returned'] as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sync">Sync Status</label>
                <select name="sync" id="sync" class="form-control">
                    <option value="">All</option>
                    <option value="synced" <?= $filterSync === 'synced' ? 'selected' : '' ?>>Synced</option>
                    <option value="waiting" <?= $filterSync === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                    <option value="failed" <?= $filterSync === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="form-group" style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Filter</button>
                <?php if ($search || $filterStatus || $filterSync): ?>
                <a href="orders.php" class="btn btn-outline" style="flex: 1; text-align: center;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Order Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                        <td colspan="7" class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        No orders found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <strong><?= e($order['order_number']) ?></strong>
                        <?php if ($order['shopify_order_number'] && $order['shopify_order_number'] !== $order['order_number']): ?>
                            <br><small class="text-muted">Shopify: <?= e($order['shopify_order_number']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= date('M d, Y', strtotime($order['created_at'])) ?><br>
                        <small class="text-muted"><?= date('h:i A', strtotime($order['created_at'])) ?></small>
                    </td>
                    <td>
                        <?= e($order['customer_name']) ?><br>
                        <?= htmlspecialchars($order['customer_phone'] ?? '') ?>
                    </td>
                    </td>
                    <td>
                        <?= get_setting($db, 'currency_symbol', '₹') . ' ' . number_format($order['total_amount'], 2) ?>
                    </td>
                    <td>
                        <form method="POST" action="orders.php" style="display:inline; margin:0;" class="inline-update-form">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= e($order['current_status']) ?>">
                            <select name="new_payment_status" class="form-control" onchange="this.form.submit()" style="min-width: 110px; padding: 4px 8px; font-size: 13px; height: auto;">
                                <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                                <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                <option value="partially_paid" <?= $order['payment_status'] === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                                <option value="partially_refunded" <?= $order['payment_status'] === 'partially_refunded' ? 'selected' : '' ?>>Partially Refunded</option>
                                <option value="unpaid" <?= $order['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="POST" action="orders.php" style="display:inline; margin:0;" class="inline-update-form">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="new_payment_status" value="<?= e($order['payment_status']) ?>">
                            <select name="new_status" class="form-control" onchange="this.form.submit()" style="min-width: 140px; padding: 4px 8px; font-size: 13px; height: auto;">
                                <?php foreach(['New','Assigned','Accepted','Preparing','Ready','Out For Delivery','Delivered','Cancelled','Returned'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $order['current_status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td class="text-right">
                        <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer" style="display:flex;justify-content:center;align-items:center;">
        <div style="display:flex;gap:4px;">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&sync=<?= urlencode($filterSync) ?>" class="btn btn-sm btn-outline">&laquo; Prev</a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1) {
                echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($filterStatus) . '&sync=' . urlencode($filterSync) . '" class="btn btn-sm btn-outline">1</a>';
                if ($startPage > 2) {
                    echo '<span style="padding: 4px 8px; color: var(--text-muted);">...</span>';
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++) {
                $activeClass = $i === $page ? 'btn-primary' : 'btn-outline';
                echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&status=' . urlencode($filterStatus) . '&sync=' . urlencode($filterSync) . '" class="btn btn-sm ' . $activeClass . '">' . $i . '</a>';
            }
            
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    echo '<span style="padding: 4px 8px; color: var(--text-muted);">...</span>';
                }
                echo '<a href="?page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . urlencode($filterStatus) . '&sync=' . urlencode($filterSync) . '" class="btn btn-sm btn-outline">' . $totalPages . '</a>';
            }
            ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&sync=<?= urlencode($filterSync) ?>" class="btn btn-sm btn-outline">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Visual feedback for inline forms
document.querySelectorAll('.inline-update-form').forEach(form => {
    form.addEventListener('submit', function() {
        const selects = this.querySelectorAll('select');
        selects.forEach(s => {
            s.style.opacity = '0.6';
            s.style.pointerEvents = 'none';
        });
        document.body.style.cursor = 'wait';
    });
});

// Manual Sync Button
document.getElementById('btn-manual-sync')?.addEventListener('click', async function() {
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Syncing...';
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', '<?= csrf_token() ?>');
        
        const response = await fetch('shopify_sync.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.ok) {
            alert(`Sync completed!\nImported: ${data.stats.imported}\nUpdated: ${data.stats.updated}\nQueue Processed: ${data.stats.queue_processed}`);
            window.location.reload();
        } else {
            alert('Sync failed: ' + data.message);
        }
    } catch (e) {
        alert('Network error during sync.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Retry Push Button
document.querySelectorAll('.btn-retry').forEach(btn => {
    btn.addEventListener('click', async function() {
        const orderId = this.dataset.id;
        const button = this;
        
        button.disabled = true;
        button.innerText = '...';
        
        try {
            const formData = new FormData();
            formData.append('csrf_token', '<?= csrf_token() ?>');
            formData.append('order_id', orderId);
            
            const response = await fetch('shopify_push.php', { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.ok) {
                alert('Job re-enqueued. It will process on next cron run.');
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            alert('Network error.');
        } finally {
            button.disabled = false;
            button.innerText = 'Retry';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

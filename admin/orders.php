<?php
/**
 * admin/orders.php
 * ---------------------------------------------------------
 * View and manage all orders. Shows sync status and allows
 * manual status updates (which enqueue to Shopify).
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

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
        $stmt = $db->prepare("SELECT o.order_number, o.shopify_order_id, o.current_status, o.payment_status, o.customer_phone, o.delivery_address, o.total_amount, b.leajlak_shop_id, o.customer_name, o.customer_email, o.leajlak_order_id, o.leajlak_status FROM orders o LEFT JOIN branches b ON o.assigned_branch_id = b.id WHERE o.id = :id");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();
        
        if ($order && (
            $order['current_status'] !== $newStatus || 
            $order['payment_status'] !== $newPaymentStatus || 
            ($newStatus === 'Ready' && in_array(strtolower($order['leajlak_status'] ?? ''), ['canceled', 'cancelled']))
        )) {
            // 1. Update local database
            $update = $db->prepare("UPDATE orders SET current_status = :status, payment_status = :pstatus, updated_at = NOW() WHERE id = :id");
            $update->execute([':status' => $newStatus, ':pstatus' => $newPaymentStatus, ':id' => $orderId]);
            
            // 2. Log history
            if ($order['current_status'] !== $newStatus) {
                $hist = $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (:oid, :old, :new, :uid)");
                $hist->execute([
                    ':oid' => $orderId,
                    ':old' => $order['current_status'],
                    ':new' => $newStatus,
                    ':uid' => (int) $_SESSION['user_id']
                ]);
            }
            
            // 2.5 Trigger Leajlak Delivery if moved to 'Ready' or Cancel if moved to 'Cancelled'
            $leajlakSuccess = true;
            $leajlakError = '';
            $leajlakCancelWarning = '';

            if (($order['current_status'] !== 'Ready' || in_array(strtolower($order['leajlak_status'] ?? ''), ['canceled', 'cancelled'])) && $newStatus === 'Ready') {
                $leajlak = new LeajlakService($db);
                $leajlakResult = $leajlak->createOrderFromRow($order);
                if (!$leajlakResult['success']) {
                    $leajlakSuccess = false;
                    $leajlakError = $leajlakResult['error'];
                    ActivityLogger::log($_SESSION['user_id'] ?? null, 'leajlak_order_failed', 'Failed to create Leajlak order: ' . $leajlakResult['error'], null, (int) $orderId);
                } else {
                    $dspId = $leajlakResult['leajlak_order_id'] ?? null;
                    if ($dspId) {
                        $db->prepare("UPDATE orders SET leajlak_order_id = :lid, leajlak_status = 'New Order', leajlak_captain_name = NULL, leajlak_captain_phone = NULL WHERE id = :id")->execute([':lid' => $dspId, ':id' => $orderId]);
                    }
                    ActivityLogger::log($_SESSION['user_id'] ?? null, 'leajlak_order_created', 'Leajlak order created: ' . ($dspId ?: ''), null, (int) $orderId);
                }
            } elseif ($order['current_status'] !== 'Cancelled' && $newStatus === 'Cancelled') {
                // Find dsp_order_id for this order
                $dspId = $order['leajlak_order_id'] ?? null;
                if (empty($dspId)) {
                    $stmtDsp = $db->prepare("SELECT leajlak_order_id FROM orders WHERE id = :id");
                    $stmtDsp->execute([':id' => $orderId]);
                    $dspId = $stmtDsp->fetchColumn();
                }
                if (empty($dspId)) {
                    $cleanNum = str_replace('#', '', $order['order_number']);
                    $stmtWh = $db->prepare("SELECT payload FROM webhook_logs WHERE topic = 'leajlak/order_status' AND (payload LIKE :p1 OR payload LIKE :p2) ORDER BY id DESC LIMIT 1");
                    $stmtWh->execute([':p1' => '%"id":"' . $cleanNum . '"%', ':p2' => '%"id":"#' . $cleanNum . '"%']);
                    $whPayload = $stmtWh->fetchColumn();
                    if ($whPayload) {
                        $pDecoded = json_decode($whPayload, true);
                        if (!empty($pDecoded['dsp_order_id'])) {
                            $dspId = (string)$pDecoded['dsp_order_id'];
                            $db->prepare("UPDATE orders SET leajlak_order_id = :lid WHERE id = :id")->execute([':lid' => $dspId, ':id' => $orderId]);
                        }
                    }
                }

                // If still empty, fall back to clean order number
                $cancelTargetId = !empty($dspId) ? $dspId : str_replace('#', '', $order['order_number']);

                $leajlak = new LeajlakService($db);
                $cancelResult = $leajlak->cancelOrder($cancelTargetId, 'Cancelled in CRM');
                if (!$cancelResult['success']) {
                    $leajlakCancelWarning = $cancelResult['error'];
                    ActivityLogger::log($_SESSION['user_id'] ?? null, 'leajlak_cancel_failed', 'Failed to cancel Leajlak order: ' . $cancelResult['error'], null, (int) $orderId);
                } else {
                    ActivityLogger::log($_SESSION['user_id'] ?? null, 'leajlak_order_cancelled', 'Cancelled Leajlak order: ' . $cancelTargetId, null, (int) $orderId);
                    $db->prepare("UPDATE orders SET leajlak_status = 'Canceled' WHERE id = :id")->execute([':id' => $orderId]);
                }
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
                
                if (!empty($leajlakCancelWarning)) {
                    flash('warning', "Order {$order['order_number']} marked Cancelled locally, but Leajlak Cancel reported: " . $leajlakCancelWarning);
                } elseif (!$leajlakSuccess) {
                    flash('warning', "Order {$order['order_number']} updated, but Leajlak Dispatch FAILED: " . $leajlakError);
                } else {
                    flash('success', "Order {$order['order_number']} updated and synced to Shopify instantly.");
                }
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
$filterPayment = $_GET['payment'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int) get_setting($db, 'orders_per_page', '25');
$offset = ($page - 1) * $limit;

$where = ["o.deleted_at IS NULL"];
$params = [];

if ($search !== '') {
    $where[] = "(o.order_number LIKE :s1 OR o.customer_name LIKE :s2 OR o.customer_phone LIKE :s3 OR o.shopify_order_number LIKE :s4)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
}
if ($filterStatus !== '') {
    $where[] = "o.current_status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterPayment !== '') {
    $where[] = "o.payment_status = :payment";
    $params[':payment'] = $filterPayment;
}

$whereSql = "WHERE " . implode(' AND ', $where);

// Total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $whereSql");
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// Fetch orders
$stmt = $db->prepare("
    SELECT o.*, b.branch_name 
    FROM orders o
    LEFT JOIN branches b ON o.assigned_branch_id = b.id
    $whereSql
    ORDER BY CAST(REPLACE(o.order_number, '#', '') AS UNSIGNED) DESC
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
    $slug = 'status-' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($s)));
    $class = match($s) {
        'New' => 'status-new',
        'Assigned', 'Accepted' => 'status-assigned',
        'Preparing' => 'status-preparing',
        'Ready' => 'status-ready',
        'Out For Delivery' => 'status-out-for-delivery',
        'Delivered' => 'status-delivered',
        'Cancelled' => 'status-cancelled',
        'Returned' => 'status-returned',
        default => 'status-pending'
    };
    return "<span class=\"status $class $slug\">" . e($s) . "</span>";
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 class="page-title">Orders</h1>
        <p class="page-subtitle">Manage, assign, and track shopify orders.</p>
    </div>
    <div>
        <button type="button" id="btn-manual-sync" class="btn btn-primary">
            <i class="fa-solid fa-rotate"></i> Sync Orders Now
        </button>
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
        <form method="GET" action="orders.php" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            <div class="form-group" style="flex: 2; min-width: 250px;">
                <label for="search">Search Orders</label>
                <input type="text" name="search" id="search" class="form-control" value="<?= e($search) ?>" placeholder="Order #, Name, Phone">
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="payment">Payment Status</label>
                <select name="payment" id="payment" class="form-control">
                    <option value="">All</option>
                    <option value="pending" <?= $filterPayment === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $filterPayment === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="refunded" <?= $filterPayment === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="status">Order Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach(array_unique(array_merge(['New','Ready','Out For Delivery','Cancelled'], [$order['current_status']])) as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 0 0 auto; display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search || $filterStatus || $filterPayment): ?>
                <a href="orders.php" class="btn btn-outline">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<style>
.orders-table-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
    background: #fff;
    overflow: hidden;
}

.orders-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 12.5px;
    table-layout: auto;
}

.orders-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 9px 8px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.orders-table td {
    padding: 8px 8px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}

.orders-table tbody tr {
    transition: background 0.15s ease;
}

.orders-table tbody tr:hover td {
    background: #f8fafc;
}

.orders-table tr:last-child td {
    border-bottom: none;
}

/* Professional typographic status badges (No AI dots, crisp micro-radius) */
.orders-table .status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2.5px 7px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.25;
    border-radius: 5px;
    letter-spacing: 0.15px;
    white-space: nowrap;
    border: 1px solid transparent;
}
.orders-table .status::before {
    display: none !important; /* Remove AI status dots */
}

/* Payment & Order Status Colors - Quiet, disciplined palette */
.orders-table .status-paid,
.orders-table .status-delivered,
.orders-table .status.completed {
    background: #f0fdf4 !important;
    color: #15803d !important;
    border-color: #bbf7d0 !important;
}

.orders-table .status-pending,
.orders-table .status.pending {
    background: #fffbeb !important;
    color: #b45309 !important;
    border-color: #fde68a !important;
}

.orders-table .status-cancelled,
.orders-table .status-failed,
.orders-table .status.cancelled,
.orders-table .status.danger {
    background: #fef2f2 !important;
    color: #991b1b !important;
    border-color: #fecaca !important;
}

.orders-table .status-refunded,
.orders-table .status-returned,
.orders-table .status.inactive {
    background: #f8fafc !important;
    color: #475569 !important;
    border-color: #cbd5e1 !important;
}

.orders-table .status-ready,
.orders-table .status.info {
    background: #f0f9ff !important;
    color: #0369a1 !important;
    border-color: #bae6fd !important;
}

.orders-table .status-out-for-delivery {
    background: #faf5ff !important;
    color: #7e22ce !important;
    border-color: #e9d5ff !important;
}

.orders-table .status-new {
    background: #eff6ff !important;
    color: #1d4ed8 !important;
    border-color: #bfdbfe !important;
}

.orders-table .status-preparing,
.orders-table .status-assigned,
.orders-table .status-accepted,
.orders-table .status.processing {
    background: #eef2ff !important;
    color: #4338ca !important;
    border-color: #c7d2fe !important;
}

/* Column specific widths & alignment */
.col-order-num {
    width: 75px;
    white-space: nowrap;
}
.order-num-pill {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    color: #0f172a;
    padding: 1px 6px;
    border-radius: 5px;
    border: 1px solid #e2e8f0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    font-weight: 700;
}

.col-date {
    width: 105px;
    white-space: nowrap;
    line-height: 1.25;
}
.date-primary {
    font-weight: 600;
    color: #1e293b;
    font-size: 12px;
}
.date-time {
    font-size: 11px;
    color: #64748b;
}

.col-customer {
    max-width: 135px;
    line-height: 1.25;
}
.customer-name {
    font-weight: 600;
    color: #0f172a;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.customer-phone {
    font-size: 11px;
    color: #64748b;
    font-family: ui-monospace, monospace;
}

/* Branch column replacing Items in admin panel */
.col-branch {
    max-width: 130px;
    line-height: 1.25;
}
.branch-pill {
    display: inline-flex;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 2.5px 7px;
    font-size: 11.5px;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.branch-pill.unassigned {
    color: #94a3b8;
    background: #fdfdfe;
    border-style: dashed;
}

.col-total {
    width: 95px;
    white-space: nowrap;
    line-height: 1.25;
}
.total-amount {
    font-weight: 700;
    color: #0f172a;
    font-size: 12.5px;
}
.shipping-tag {
    font-size: 10.5px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 2px;
}

.col-payment {
    width: 85px;
    white-space: nowrap;
    text-align: center;
}

.col-status {
    width: 100px;
    white-space: nowrap;
    text-align: center;
}

.col-action {
    width: 150px;
    text-align: center;
    white-space: nowrap;
}

/* Compact high-precision action buttons */
.btn-action-ready {
    background: #10b981 !important;
    border: 1px solid #059669 !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    font-size: 11.5px !important;
    padding: 4px 9px !important;
    border-radius: 5px !important;
    gap: 4px !important;
    box-shadow: 0 1px 2px rgba(16, 185, 129, 0.2);
    transition: all 0.15s ease;
}
.btn-action-ready:hover {
    background: #059669 !important;
    transform: translateY(-1px);
}

.btn-action-cancel {
    background: #fef2f2 !important;
    border: 1px solid #fecaca !important;
    color: #dc2626 !important;
    font-weight: 600 !important;
    font-size: 11.5px !important;
    padding: 4px 8px !important;
    border-radius: 5px !important;
    gap: 4px !important;
    transition: all 0.15s ease;
}
.btn-action-cancel:hover {
    background: #fee2e2 !important;
    color: #b91c1c !important;
}

.btn-action-reready {
    background: #fffbeb !important;
    border: 1px solid #fde68a !important;
    color: #b45309 !important;
    font-weight: 600 !important;
    font-size: 11.5px !important;
    padding: 4px 8px !important;
    border-radius: 5px !important;
    gap: 4px !important;
    transition: all 0.15s ease;
}
.btn-action-reready:hover {
    background: #fef3c7 !important;
    color: #92400e !important;
}

.btn-action-view {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #475569 !important;
    font-weight: 500 !important;
    font-size: 11.5px !important;
    padding: 4px 8px !important;
    border-radius: 5px !important;
    transition: all 0.15s ease;
}
.btn-action-view:hover {
    background: #f8fafc !important;
    color: #0f172a !important;
}
</style>

<!-- Data Table -->
<div class="card orders-table-card">
    <div class="table-responsive" style="overflow-x: hidden;">
        <table class="orders-table">
            <thead>
                <tr>
                    <th class="col-order-num">Order #</th>
                    <th class="col-date">Date</th>
                    <th class="col-customer">Customer</th>
                    <th class="col-branch">Branch</th>
                    <th class="col-total">Total</th>
                    <th class="col-payment" style="text-align: center;">Payment</th>
                    <th class="col-status" style="text-align: center;">Order Status</th>
                    <th class="col-action" style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" class="empty-state" style="padding: 30px 15px; text-align: center; color: var(--text-muted);">
                        <i class="fa-solid fa-box-open" style="font-size: 24px; margin-bottom: 6px; display: block; opacity: 0.5;"></i>
                        No orders found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="col-order-num">
                        <span class="order-num-pill"><?= e($order['order_number']) ?></span>
                        <?php if ($order['shopify_order_number'] && $order['shopify_order_number'] !== $order['order_number']): ?>
                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Shopify: <?= e($order['shopify_order_number']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="col-date">
                        <div class="date-primary"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                        <div class="date-time"><?= date('h:i A', strtotime($order['created_at'])) ?></div>
                    </td>
                    <td class="col-customer">
                        <div class="customer-name" title="<?= e($order['customer_name']) ?>"><?= e($order['customer_name']) ?></div>
                        <div class="customer-phone"><?= htmlspecialchars($order['customer_phone'] ?? '') ?></div>
                    </td>
                    <td class="col-branch">
                        <?php if ($order['branch_name']): ?>
                            <span class="branch-pill" title="<?= e($order['branch_name']) ?>"><?= e($order['branch_name']) ?></span>
                        <?php else: ?>
                            <span class="branch-pill unassigned">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-total">
                        <div class="total-amount"><?= get_setting($db, 'currency_symbol', 'SAR') . ' ' . number_format($order['total_amount'], 2) ?></div>
                        <?php if (isset($order['shipping_fee']) && (float)$order['shipping_fee'] > 0): ?>
                            <div class="shipping-tag" title="Shipping Fee">
                                <i class="fa-solid fa-truck" style="font-size: 0.65rem;"></i> +<?= number_format($order['shipping_fee'], 2) ?> ship
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-payment">
                        <?php
                        $pStatus = $order['payment_status'] ?? 'pending';
                        $pSlug = 'status-pay-' . strtolower(str_replace([' ', '_'], '-', trim($pStatus)));
                        $pClass = match($pStatus) {
                            'paid' => 'status-paid',
                            'refunded', 'partially_refunded' => 'status-refunded',
                            'failed' => 'status-failed',
                            default => 'status-pending'
                        };
                        ?>
                        <span class="status <?= $pClass ?> <?= $pSlug ?>"><?= ucfirst(str_replace('_', ' ', $pStatus)) ?></span>
                    </td>
                    <td class="col-status">
                        <?= statusBadge($order['current_status']) ?>
                    </td>
                    <td class="col-action">
                        <div style="display:inline-flex; gap:5px; align-items:center; justify-content:center;">
                            <?php if (in_array($order['current_status'], ['New', 'Assigned', 'Accepted', 'Preparing'])): ?>
                                <button type="button" class="btn btn-sm btn-action-ready"
                                        data-id="<?= $order['id'] ?>"
                                        data-number="<?= e($order['order_number']) ?>"
                                        data-payment="<?= e($order['payment_status']) ?>"
                                        title="Mark order as Ready and dispatch delivery captain in Leajlak">
                                    <i class="fa-solid fa-check"></i> Ready
                                </button>
                            <?php elseif ($order['current_status'] === 'Ready'): ?>
                                <button type="button" class="btn btn-sm btn-action-cancel"
                                        data-id="<?= $order['id'] ?>"
                                        data-number="<?= e($order['order_number']) ?>"
                                        data-payment="<?= e($order['payment_status']) ?>"
                                        title="Cancel order and recall Leajlak delivery driver">
                                    <i class="fa-solid fa-xmark"></i> Cancel
                                </button>
                            <?php elseif ($order['current_status'] === 'Cancelled'): ?>
                                <button type="button" class="btn btn-sm btn-action-reready"
                                        data-id="<?= $order['id'] ?>"
                                        data-number="<?= e($order['order_number']) ?>"
                                        data-payment="<?= e($order['payment_status']) ?>"
                                        title="Re-dispatch order to Leajlak and mark Ready">
                                    <i class="fa-solid fa-rotate-right"></i> Re-Dispatch
                                </button>
                            <?php endif; ?>

                            <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-action-view">View</a>
                        </div>
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

<!-- Hidden form for Quick Direct Dispatch (Ready) -->
<form id="quick-status-form" method="POST" action="orders.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="order_id" id="quick_order_id" value="">
    <input type="hidden" name="new_status" id="quick_new_status" value="">
    <input type="hidden" name="new_payment_status" id="quick_payment_status" value="pending">
</form>

<!-- Modal: Cancel Order Confirmation -->
<div class="modal-overlay" id="modal-cancel-order">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="orders.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="modal_cancel_order_id" value="">
            <input type="hidden" name="new_status" value="Cancelled">
            <input type="hidden" name="new_payment_status" id="modal_cancel_payment_status" value="pending">
            
            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Cancel Order
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-cancel-order')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 20px 24px;">
                <p style="margin: 0; font-size: 15px; color: var(--text-main);">
                    Are you sure you want to cancel <strong id="modal_cancel_order_number"></strong>?
                </p>
                <p style="margin: 8px 0 0; font-size: 13px; color: var(--text-muted); line-height: 1.45;">
                    This will mark the order as <strong>Cancelled</strong> and notify Leajlak to dismiss the delivery captain.
                </p>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-cancel-order')">No, Keep Order</button>
                <button type="submit" class="btn btn-danger" id="btn-submit-cancel">
                    <i class="fa-solid fa-xmark"></i> Yes, Cancel Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Re-Ready Confirmation -->
<div class="modal-overlay" id="modal-reready-order">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="orders.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="modal_reready_order_id" value="">
            <input type="hidden" name="new_status" value="Ready">
            <input type="hidden" name="new_payment_status" id="modal_reready_payment_status" value="pending">
            
            <div class="modal-header">
                <h3 class="modal-title" style="color: #b45309; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-rotate-right"></i> Re-Dispatch Order
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-reready-order')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 20px 24px;">
                <p style="margin: 0; font-size: 15px; color: var(--text-main);">
                    Re-dispatch <strong id="modal_reready_order_number"></strong> for delivery?
                </p>
                <p style="margin: 8px 0 0; font-size: 13px; color: var(--text-muted); line-height: 1.45;">
                    This will mark the order as <strong>Ready</strong> and send a fresh driver dispatch request to Leajlak.
                </p>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-reready-order')">Cancel</button>
                <button type="submit" class="btn btn-warning" id="btn-submit-reready">
                    <i class="fa-solid fa-check"></i> Yes, Re-Dispatch
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function closeModal(id) {
    document.getElementById(id)?.classList.remove('show');
}

// Close modals on backdrop click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// 1. Ready button handler (Immediate 1-click dispatch with spinner)
document.querySelectorAll('.btn-action-ready').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.disabled) return;
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Dispatching...';
        
        document.getElementById('quick_order_id').value = this.dataset.id;
        document.getElementById('quick_new_status').value = 'Ready';
        document.getElementById('quick_payment_status').value = this.dataset.payment || 'pending';
        document.getElementById('quick-status-form').submit();
    });
});

// 2. Cancel button handler (Opens modal)
document.querySelectorAll('.btn-action-cancel').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('modal_cancel_order_id').value = this.dataset.id;
        document.getElementById('modal_cancel_order_number').innerText = this.dataset.number;
        document.getElementById('modal_cancel_payment_status').value = this.dataset.payment || 'pending';
        document.getElementById('modal-cancel-order').classList.add('show');
    });
});

// Cancel submit loading state
document.getElementById('modal-cancel-order')?.querySelector('form')?.addEventListener('submit', function() {
    const btn = document.getElementById('btn-submit-cancel');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cancelling...';
    }
});

// 3. Re-Ready button handler (Opens modal)
document.querySelectorAll('.btn-action-reready').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('modal_reready_order_id').value = this.dataset.id;
        document.getElementById('modal_reready_order_number').innerText = this.dataset.number;
        document.getElementById('modal_reready_payment_status').value = this.dataset.payment || 'pending';
        document.getElementById('modal-reready-order').classList.add('show');
    });
});

// Re-ready submit loading state
document.getElementById('modal-reready-order')?.querySelector('form')?.addEventListener('submit', function() {
    const btn = document.getElementById('btn-submit-reready');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Dispatching...';
    }
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










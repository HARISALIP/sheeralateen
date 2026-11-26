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
    die('<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access Denied</title><style>body{font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f7fa;text-align:center;} .box{background:#fff;padding:40px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.05);max-width:400px;width:90%;} h2{margin-top:0;color:#1e293b;} p{color:#475569;margin-bottom:24px;line-height:1.5;} .btn{display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;transition:0.2s;} .btn:hover{background:#4338ca;}</style></head><body><div class="box"><h2>Access Denied</h2><p>Error: No branch has been assigned to your manager account yet.<br><br>Please contact the Super Admin to assign you to a branch.</p><a href="logout.php" class="btn">Log Out & Return</a></div></body></html>');
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
    
    // Automatically set payment to paid if order is marked Delivered
    if ($newStatus === 'Delivered' && (empty($newPaymentStatus) || $newPaymentStatus === 'pending')) {
        $newPaymentStatus = 'paid';
    }
    
    // valid statuses
    $validStatuses = ['New', 'Assigned', 'Accepted', 'Preparing', 'Ready', 'Out For Delivery', 'Delivered', 'Cancelled', 'Returned'];
    $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_paid', 'partially_refunded', 'unpaid'];
    
    if ($orderId > 0 && in_array($newStatus, $validStatuses) && in_array($newPaymentStatus, $validPaymentStatuses)) {
        // Fetch order to verify and log
        $stmt = $db->prepare("SELECT o.order_number, o.shopify_order_id, o.current_status, o.payment_status, o.customer_phone, o.delivery_address, o.total_amount, b.leajlak_shop_id, o.customer_name, o.customer_email, o.leajlak_order_id, o.leajlak_status FROM orders o LEFT JOIN branches b ON o.assigned_branch_id = b.id WHERE o.id = :id AND o.assigned_branch_id = :bid");
        $stmt->execute([':id' => $orderId, ':bid' => $branchId]);
        $order = $stmt->fetch();
        
        $shouldUpdate = $order && (
            $order['current_status'] !== $newStatus || 
            $order['payment_status'] !== $newPaymentStatus || 
            ($newStatus === 'Ready' && in_array(strtolower($order['leajlak_status'] ?? ''), ['canceled', 'cancelled']))
        );

        if ($shouldUpdate) {
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

// --- POST HANDLER: Update Payment Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security token mismatch.');
        header('Location: orders.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newPaymentStatus = strtolower(trim($_POST['new_payment_status'] ?? ''));
    $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_paid', 'partially_refunded', 'unpaid'];

    if ($orderId > 0 && in_array($newPaymentStatus, $validPaymentStatuses)) {
        $stmt = $db->prepare("SELECT order_number, shopify_order_id, payment_status FROM orders WHERE id = :id AND assigned_branch_id = :bid AND deleted_at IS NULL");
        $stmt->execute([':id' => $orderId, ':bid' => $branchId]);
        $order = $stmt->fetch();

        if ($order && $order['payment_status'] !== $newPaymentStatus) {
            $oldPayment = $order['payment_status'];
            $db->prepare("UPDATE orders SET payment_status = :pstatus, updated_at = NOW() WHERE id = :id")
               ->execute([':pstatus' => $newPaymentStatus, ':id' => $orderId]);

            ActivityLogger::log($_SESSION['user_id'] ?? null, 'payment_status_update', "Order {$order['order_number']} payment changed: {$oldPayment} -> {$newPaymentStatus}", null, $orderId);

            if ($order['shopify_order_id']) {
                $queue = new SyncQueue($db);
                $queue->enqueue('order', $orderId, $order['shopify_order_id'], 'push_payment');
                try {
                    $api = new ShopifyService($db);
                    $sync = new ShopifySyncService($db, $api, $queue);
                    $sync->processQueue(5);
                } catch (Exception $e) {}
            }

            flash('success', "Order {$order['order_number']} payment status updated to " . ucfirst(str_replace('_', ' ', $newPaymentStatus)) . ".");
        }
    }
    header('Location: orders.php');
    exit;
}

// Auto-calibrate: ensure any Delivered order with non-paid payment status is set to paid for this branch
try {
    $db->prepare("UPDATE orders SET payment_status = 'paid', updated_at = NOW() WHERE current_status = 'Delivered' AND payment_status != 'paid' AND assigned_branch_id = :bid AND deleted_at IS NULL")->execute([':bid' => $branchId]);
} catch (\Throwable $e) {}

// --- FILTER & PAGINATION ---
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterPayment = $_GET['payment'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int) get_setting($db, 'orders_per_page', '25');
$offset = ($page - 1) * $limit;

$where = ["o.deleted_at IS NULL", "o.assigned_branch_id = $branchId"];
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
    SELECT o.* 
    FROM orders o
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

// Fetch items for all displayed orders
$orderIds = array_column($orders, 'id');
$itemsByOrder = [];
if (!empty($orderIds)) {
    $inClause = implode(',', array_map('intval', $orderIds));
    $itemsStmt = $db->query("SELECT order_id, item_name, quantity FROM order_items WHERE order_id IN ($inClause) ORDER BY id ASC");
    while ($it = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        $itemsByOrder[$it['order_id']][] = $it;
    }
}

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

<!-- Filters (Single Line Row) -->
<div class="card filter-bar-card">
    <div class="card-body">
        <form method="GET" action="orders.php" class="filter-bar-form">
            <div class="filter-field field-search">
                <label for="search">Search Orders</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" id="search" class="form-control" value="<?= e($search) ?>" placeholder="Order #, Name, Phone">
                </div>
            </div>
            <div class="filter-field field-select">
                <label for="payment">Payment Status</label>
                <select name="payment" id="payment" class="form-control">
                    <option value="">All Payments</option>
                    <option value="pending" <?= $filterPayment === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $filterPayment === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="refunded" <?= $filterPayment === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="filter-field field-select">
                <label for="status">Order Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach(['New','Ready','Out For Delivery','Cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-filter">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <?php if ($search || $filterStatus || $filterPayment): ?>
                <a href="orders.php" class="btn btn-outline btn-clear" title="Clear Filters">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<style>
/* Single-line filter bar styling */
.filter-bar-card {
    margin-bottom: 20px;
    border-radius: 8px;
    border: 1px solid #E8ECF2;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    background: #ffffff;
}
.filter-bar-card .card-body {
    padding: 12px 18px;
}
.filter-bar-form {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 12px;
    width: 100%;
}
.filter-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.filter-field label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #778399;
    margin: 0;
    white-space: nowrap;
}
.filter-field.field-search {
    flex: 2;
    min-width: 180px;
}
.filter-field.field-select {
    flex: 1.2;
    min-width: 130px;
}
.input-with-icon {
    position: relative;
    width: 100%;
}
.input-with-icon i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13px;
    pointer-events: none;
}
.input-with-icon input {
    padding-left: 32px !important;
}
.filter-bar-form .form-control {
    height: 38px;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid #E8ECF2;
    background: #ffffff;
    color: #172033;
    width: 100%;
}
.filter-bar-form .form-control:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}
.filter-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.filter-actions .btn-filter {
    height: 38px;
    padding: 0 16px;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.filter-actions .btn-clear {
    height: 38px;
    padding: 0 12px;
    font-size: 12.5px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #778399;
    border: 1px solid #E8ECF2;
    white-space: nowrap;
}
.filter-actions .btn-clear:hover {
    background: #F8FAFC;
    color: #172033;
}
.orders-table-card {
    border-radius: 8px;
    border: 1px solid #E8ECF2;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    background: #ffffff;
    overflow: hidden;
}

.orders-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 12.5px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    table-layout: auto;
    background: #ffffff;
}

.orders-table th {
    background: #F8FAFC;
    color: #778399;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 11px 10px;
    border-bottom: 1px solid #E8ECF2;
    vertical-align: middle !important;
    white-space: nowrap;
}

.orders-table tbody tr {
    height: 62px;
    transition: background 0.15s ease;
}

.orders-table td {
    padding: 10px 10px;
    height: 62px;
    border-bottom: 1px solid #EEF2F6;
    color: #172033;
    vertical-align: middle !important;
    box-sizing: border-box;
}

.orders-table tbody tr:hover td {
    background: #FAFBFC;
}

.orders-table tr:last-child td {
    border-bottom: none;
}

/* Status pill badges matching reference design (Pill shape, exact soft pastels, no dots) */
.orders-table .status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 3.5px 13px;
    font-size: 11.5px;
    font-weight: 600;
    line-height: 1.25;
    border-radius: 9999px !important; /* Full rounded pill */
    letter-spacing: 0.1px;
    white-space: nowrap;
    border: none !important;
    box-shadow: none !important;
}
.orders-table .status::before {
    display: none !important; /* Remove AI status dots */
}

/* Payment & Order Status Colors matching image */
.orders-table .status-paid,
.orders-table .status-delivered,
.orders-table .status.completed {
    background: #dcfce7 !important;
    color: #166534 !important;
}

.orders-table .status-pending,
.orders-table .status.pending {
    background: #fef08a !important;
    color: #854d0e !important;
}

.orders-table .status-cancelled,
.orders-table .status-failed,
.orders-table .status.cancelled,
.orders-table .status.danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
}

.orders-table .status-refunded,
.orders-table .status-returned,
.orders-table .status.inactive {
    background: #f1f5f9 !important;
    color: #475569 !important;
}

.orders-table .status-ready,
.orders-table .status.info {
    background: #e0f2fe !important;
    color: #0369a1 !important;
}

.orders-table .status-out-for-delivery {
    background: #f3e8ff !important;
    color: #6b21a8 !important;
}

.orders-table .status-new {
    background: #e0e7ff !important;
    color: #3730a3 !important;
}

.orders-table .status-preparing,
.orders-table .status-assigned,
.orders-table .status-accepted,
.orders-table .status.processing {
    background: #ede9fe !important;
    color: #5b21b6 !important;
}

/* Column specific widths & alignment */
.col-order-num {
    width: 80px;
    white-space: nowrap;
}
.order-num-pill {
    display: inline-flex;
    align-items: center;
    background: #F8FAFC;
    color: #0F172A;
    padding: 2.5px 8px;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
    font-family: 'Inter', ui-monospace, monospace;
    font-size: 12.5px;
    font-weight: 700;
    letter-spacing: -0.2px;
}

.col-date {
    width: 110px;
    white-space: nowrap;
    line-height: 1.3;
}
.date-primary {
    font-weight: 600;
    color: #172033;
    font-size: 12px;
}
.date-time {
    font-size: 10.5px;
    color: #778399;
    font-weight: 400;
    margin-top: 1px;
}

.col-customer {
    max-width: 140px;
    line-height: 1.3;
}
.customer-name {
    font-weight: 600;
    color: #0F172A;
    font-size: 13px;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.customer-phone {
    font-size: 11px;
    color: #94A3B8;
    font-family: 'Inter', ui-monospace, monospace;
    font-weight: 400;
    margin-top: 2px;
    letter-spacing: 0.1px;
}

.col-items {
    max-width: 180px;
}
.item-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    line-height: 1.3;
    color: #172033;
    max-width: 100%;
    margin-bottom: 2px;
}
.item-label:last-child {
    margin-bottom: 0;
}
.item-qty {
    font-weight: 700;
    font-size: 11px;
    color: #4f46e5;
    background: #eef2ff;
    padding: 1px 5px;
    border-radius: 4px;
    letter-spacing: -0.2px;
    flex-shrink: 0;
}
.item-name {
    font-size: 12px;
    color: #172033;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.item-more {
    font-size: 11px;
    color: #778399;
    margin-left: 2px;
    font-weight: 500;
    display: inline-block;
}

.col-total {
    width: 115px;
    white-space: nowrap;
    line-height: 1.3;
}
.total-amount {
    font-weight: 700;
    color: #172033;
    font-size: 13px;
    letter-spacing: -0.2px;
    white-space: nowrap;
    display: block;
}
.shipping-tag {
    font-size: 11px;
    color: #778399;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
    white-space: nowrap;
}
.shipping-tag i {
    color: #94a3b8;
    font-size: 10px;
    opacity: 0.85;
}

.col-payment {
    width: 105px;
    white-space: nowrap;
    text-align: center !important;
}

.col-status {
    width: 115px;
    white-space: nowrap;
    text-align: center !important;
}

.col-action {
    width: 215px;
    text-align: center !important;
    white-space: nowrap;
}

.action-cell-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 190px;
}

.action-step-slot {
    width: 104px;
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
}

/* Compact outlined action buttons (Re-Dispatch, Ready, Cancel) */
.btn-action-reready {
    background: #ffffff !important;
    border: 1px solid #3b82f6 !important;
    color: #2563eb !important;
    font-weight: 500 !important;
    font-size: 11.5px !important;
    height: 29px !important;
    padding: 0 10px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    white-space: nowrap !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}
.btn-action-reready:hover {
    background: #eff6ff !important;
    border-color: #1d4ed8 !important;
    color: #1d4ed8 !important;
}

.btn-action-ready {
    background: #ffffff !important;
    border: 1px solid #22c55e !important;
    color: #16a34a !important;
    font-weight: 500 !important;
    font-size: 11.5px !important;
    height: 29px !important;
    padding: 0 10px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    white-space: nowrap !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}
.btn-action-ready:hover {
    background: #f0fdf4 !important;
    border-color: #15803d !important;
    color: #15803d !important;
}

.btn-action-cancel {
    background: #ffffff !important;
    border: 1px solid #ef4444 !important;
    color: #dc2626 !important;
    font-weight: 500 !important;
    font-size: 11.5px !important;
    height: 29px !important;
    padding: 0 10px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    white-space: nowrap !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}
.btn-action-cancel:hover {
    background: #fef2f2 !important;
    border-color: #b91c1c !important;
    color: #b91c1c !important;
}

/* Consistent secondary button (View) */
.btn-action-view {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    color: #334155 !important;
    font-weight: 500 !important;
    font-size: 12px !important;
    height: 29px !important;
    padding: 0 12px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    white-space: nowrap !important;
    text-decoration: none !important;
    transition: all 0.15s ease !important;
}
.btn-action-view:hover {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

/* Three-dots menu for less frequently used actions */
.action-menu-wrapper {
    position: relative;
    display: inline-flex;
}
.btn-action-more {
    background: transparent;
    border: 1px solid transparent;
    width: 29px;
    height: 29px;
    color: #778399;
    font-size: 13px;
    cursor: pointer;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: all 0.15s ease;
}
.btn-action-more:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #0f172a;
}
.action-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
    min-width: 170px;
    z-index: 100;
    padding: 5px;
    text-align: left;
}
.action-dropdown-menu.show {
    display: block;
}
.action-dropdown-menu .dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 7px 10px;
    font-size: 12px;
    font-weight: 500;
    color: #334155;
    text-decoration: none;
    border: none;
    background: transparent;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.12s ease, color 0.12s ease;
}
.action-dropdown-menu .dropdown-item:hover {
    background: #f8fafc;
    color: #0f172a;
}
.action-dropdown-menu .dropdown-item i {
    width: 14px;
    text-align: center;
    font-size: 12px;
}
.action-dropdown-menu .dropdown-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 4px 0;
}
.action-dropdown-menu .dropdown-item.text-danger {
    color: #dc2626;
}
.action-dropdown-menu .dropdown-item.text-danger:hover {
    background: #fef2f2;
}
.action-dropdown-menu .dropdown-item.text-primary {
    color: #2563eb;
}
.action-dropdown-menu .dropdown-item.text-primary:hover {
    background: #eff6ff;
}

.orders-table-card .table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.orders-table-card .table-responsive::-webkit-scrollbar {
    height: 5px;
}
.orders-table-card .table-responsive::-webkit-scrollbar-track {
    background: transparent;
}
.orders-table-card .table-responsive::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.orders-table-card .table-responsive::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>

<div class="card orders-table-card">
    <div class="table-responsive">
        <table class="orders-table">
            <thead>
                <tr>
                    <th class="col-order-num">Order #</th>
                    <th class="col-date">Date</th>
                    <th class="col-customer">Customer</th>
                    <th class="col-items">Items</th>
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
                    <td class="col-items">
                        <?php 
                        $orderItemList = $itemsByOrder[$order['id']] ?? [];
                        if (!empty($orderItemList)): 
                            $shownItems = array_slice($orderItemList, 0, 2);
                            $remaining = count($orderItemList) - count($shownItems);
                        ?>
                            <div>
                                <?php foreach ($shownItems as $it): ?>
                                    <div class="item-label" title="<?= e($it['item_name']) ?>">
                                        <span class="item-qty"><?= (int)$it['quantity'] ?>x</span>
                                        <span class="item-name"><?= e($it['item_name']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($remaining > 0): ?>
                                    <span class="item-more" title="<?= e(implode(', ', array_map(fn($x) => $x['quantity'] . 'x ' . $x['item_name'], array_slice($orderItemList, 2)))) ?>">
                                        +<?= $remaining ?> more
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted" style="font-size: 11px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-total">
                        <div class="total-amount"><?= get_setting($db, 'currency_symbol', 'SAR') . ' ' . number_format($order['total_amount'], 2) ?></div>
                        <?php if (isset($order['shipping_fee']) && (float)$order['shipping_fee'] > 0): ?>
                            <div class="shipping-tag" title="Shipping Fee">
                                <i class="fa-solid fa-truck"></i> +<?= number_format($order['shipping_fee'], 2) ?> shipping
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
                        <span class="status <?= $pClass ?> <?= $pSlug ?> btn-trigger-payment"
                              data-id="<?= $order['id'] ?>"
                              data-number="<?= e($order['order_number']) ?>"
                              data-payment="<?= e($pStatus) ?>"
                              style="cursor: pointer; display: inline-flex; align-items: center; gap: 5px;"
                              title="Click to change payment status">
                            <?= ucfirst(str_replace('_', ' ', $pStatus)) ?> <i class="fa-solid fa-pen" style="font-size: 0.65rem; opacity: 0.6;"></i>
                        </span>
                    </td>
                    <td class="col-status">
                        <?= statusBadge($order['current_status']) ?>
                    </td>
                    <td class="col-action">
                        <div class="action-cell-wrap">
                            <div class="action-step-slot">
                                <?php if (in_array($order['current_status'], ['New', 'Assigned', 'Accepted', 'Preparing'])): ?>
                                    <button type="button" class="btn btn-sm btn-action-ready"
                                            data-id="<?= $order['id'] ?>"
                                            data-number="<?= e($order['order_number']) ?>"
                                            data-payment="<?= e($order['payment_status']) ?>"
                                            title="Mark Ready & Dispatch">
                                        <i class="fa-solid fa-check"></i> Ready
                                    </button>
                                <?php elseif ($order['current_status'] === 'Ready'): ?>
                                    <button type="button" class="btn btn-sm btn-action-cancel"
                                            data-id="<?= $order['id'] ?>"
                                            data-number="<?= e($order['order_number']) ?>"
                                            data-payment="<?= e($order['payment_status']) ?>"
                                            title="Cancel Order & Recall Driver">
                                        <i class="fa-solid fa-xmark"></i> Cancel
                                    </button>
                                <?php elseif ($order['current_status'] === 'Cancelled'): ?>
                                    <button type="button" class="btn btn-sm btn-action-reready"
                                            data-id="<?= $order['id'] ?>"
                                            data-number="<?= e($order['order_number']) ?>"
                                            data-payment="<?= e($order['payment_status']) ?>"
                                            title="Re-dispatch to Leajlak">
                                        <i class="fa-solid fa-rotate-right"></i> Re-Dispatch
                                    </button>
                                <?php endif; ?>
                            </div>

                            <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-action-view">View</a>

                            <div class="action-menu-wrapper">
                                <button type="button" class="btn-action-more" aria-label="More actions" title="More options">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <div class="action-dropdown-menu">
                                    <a href="order_details.php?id=<?= $order['id'] ?>" class="dropdown-item">
                                        <i class="fa-solid fa-file-invoice"></i> Order Details
                                    </a>
                                    <a href="order_details.php?id=<?= $order['id'] ?>" target="_blank" class="dropdown-item">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in New Tab
                                    </a>
                                    <?php if (!empty($order['customer_phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>" class="dropdown-item">
                                            <i class="fa-solid fa-phone"></i> Call Customer
                                        </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <button type="button" class="dropdown-item text-success btn-menu-payment"
                                            data-id="<?= $order['id'] ?>"
                                            data-number="<?= e($order['order_number']) ?>"
                                            data-payment="<?= e($pStatus) ?>">
                                        <i class="fa-solid fa-credit-card"></i> Change Payment Status
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($order['current_status'] !== 'Cancelled'): ?>
                                        <button type="button" class="dropdown-item text-danger btn-menu-cancel"
                                                data-id="<?= $order['id'] ?>"
                                                data-number="<?= e($order['order_number']) ?>"
                                                data-payment="<?= e($order['payment_status']) ?>">
                                            <i class="fa-solid fa-ban"></i> Cancel Order
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="dropdown-item text-primary btn-menu-reready"
                                                data-id="<?= $order['id'] ?>"
                                                data-number="<?= e($order['order_number']) ?>"
                                                data-payment="<?= e($order['payment_status']) ?>">
                                            <i class="fa-solid fa-rotate-right"></i> Re-Dispatch
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
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

<!-- Modal: Change Payment Status -->
<div class="modal-overlay" id="modal-payment-status">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="orders.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_payment_status">
            <input type="hidden" name="order_id" id="modal_payment_order_id" value="">
            
            <div class="modal-header">
                <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-credit-card" style="color: var(--primary);"></i> Payment Status
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-payment-status')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 20px 24px;">
                <p style="margin: 0 0 16px; font-size: 14px; color: var(--text-main);">
                    Update payment status for order <strong id="modal_payment_order_number"></strong>:
                </p>
                <div class="form-group">
                    <label for="select_payment_status" style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500;">Payment Status</label>
                    <select name="new_payment_status" id="select_payment_status" class="form-control" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;" required>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="refunded">Refunded</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-payment-status')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-submit-payment">
                    <i class="fa-solid fa-check"></i> Save & Sync
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

// 4. Toggle action dropdown menu (three-dots)
document.querySelectorAll('.btn-action-more').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const menu = this.nextElementSibling;
        const isOpen = menu.classList.contains('show');
        document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        if (!isOpen) {
            menu.classList.add('show');
        }
    });
});

// Close action dropdowns on clicking anywhere outside
document.addEventListener('click', function() {
    document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
});

// Dropdown item: Cancel trigger
document.querySelectorAll('.btn-menu-cancel').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        document.getElementById('modal_cancel_order_id').value = this.dataset.id;
        document.getElementById('modal_cancel_order_number').innerText = this.dataset.number;
        document.getElementById('modal_cancel_payment_status').value = this.dataset.payment || 'pending';
        document.getElementById('modal-cancel-order').classList.add('show');
    });
});

// Dropdown item: Re-Dispatch trigger
document.querySelectorAll('.btn-menu-reready').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        document.getElementById('modal_reready_order_id').value = this.dataset.id;
        document.getElementById('modal_reready_order_number').innerText = this.dataset.number;
        document.getElementById('modal_reready_payment_status').value = this.dataset.payment || 'pending';
        document.getElementById('modal-reready-order').classList.add('show');
    });
});

// 5. Payment Modal triggers (badge click & menu click)
function openPaymentModal(orderId, orderNumber, paymentStatus) {
    document.getElementById('modal_payment_order_id').value = orderId;
    document.getElementById('modal_payment_order_number').innerText = orderNumber;
    const sel = document.getElementById('select_payment_status');
    if (sel) {
        sel.value = paymentStatus || 'pending';
    }
    document.getElementById('modal-payment-status').classList.add('show');
}

document.querySelectorAll('.btn-trigger-payment, .btn-menu-payment').forEach(el => {
    el.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        openPaymentModal(this.dataset.id, this.dataset.number, this.dataset.payment);
    });
});

document.getElementById('modal-payment-status')?.querySelector('form')?.addEventListener('submit', function() {
    const btn = document.getElementById('btn-submit-payment');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>








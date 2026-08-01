<?php
/**
 * admin/order_details.php
 * ---------------------------------------------------------
 * View full details of a specific order (Admin View).
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

// --- POST HANDLER: Update Order Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security token mismatch.');
        header('Location: order_details.php?id=' . (int)($_POST['order_id'] ?? 0));
        exit;
    }

    $updateOrderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $newPaymentStatus = $_POST['new_payment_status'] ?? '';
    
    // valid statuses
    $validStatuses = ['New', 'Assigned', 'Accepted', 'Preparing', 'Ready', 'Out For Delivery', 'Delivered', 'Cancelled', 'Returned'];
    $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_paid', 'partially_refunded', 'unpaid'];
    
    if ($updateOrderId > 0 && in_array($newStatus, $validStatuses) && in_array($newPaymentStatus, $validPaymentStatuses)) {
        $stmt = $db->prepare("SELECT order_number, shopify_order_id, current_status, payment_status FROM orders WHERE id = :id");
        $stmt->execute([':id' => $updateOrderId]);
        $orderCheck = $stmt->fetch();
        
        if ($orderCheck && ($orderCheck['current_status'] !== $newStatus || $orderCheck['payment_status'] !== $newPaymentStatus)) {
            $update = $db->prepare("UPDATE orders SET current_status = :status, payment_status = :pstatus, updated_at = NOW() WHERE id = :id");
            $update->execute([':status' => $newStatus, ':pstatus' => $newPaymentStatus, ':id' => $updateOrderId]);
            
            if ($orderCheck['current_status'] !== $newStatus) {
                $hist = $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (:oid, :old, :new, :uid)");
                $hist->execute([
                    ':oid' => $updateOrderId,
                    ':old' => $orderCheck['current_status'],
                    ':new' => $newStatus,
                    ':uid' => (int) $_SESSION['user_id']
                ]);
            }
            
            if ($orderCheck['shopify_order_id']) {
                $queue = new SyncQueue($db);
                if ($orderCheck['current_status'] !== $newStatus) {
                    $queue->enqueue('order', $updateOrderId, $orderCheck['shopify_order_id'], 'push_status');
                }
                if ($orderCheck['payment_status'] !== $newPaymentStatus) {
                    $queue->enqueue('order', $updateOrderId, $orderCheck['shopify_order_id'], 'push_payment');
                }
            }
            
            flash('success', 'Order #' . e($orderCheck['order_number']) . ' updated and synced to Shopify.');
        }
    }
    header('Location: order_details.php?id=' . $updateOrderId);
    exit;
}

$orderId = (int) ($_GET['id'] ?? 0);

if (!$orderId) {
    flash('error', 'Invalid order ID.');
    header('Location: orders.php');
    exit;
}

// Fetch order details and the assigned branch name
$stmt = $db->prepare("
    SELECT o.*, b.branch_name 
    FROM orders o 
    LEFT JOIN branches b ON o.assigned_branch_id = b.id 
    WHERE o.id = :id AND o.deleted_at IS NULL
");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    header('Location: orders.php');
    exit;
}

// Fetch order items
$itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC");
$itemStmt->execute([':id' => $orderId]);
$items = $itemStmt->fetchAll();

$pageTitle = 'Order Details #' . $order['order_number'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

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

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 class="page-title">Order <?= e($order['order_number']) ?></h1>
        <p class="page-subtitle"><?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></p>
    </div>
    <div>
        <a href="orders.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Back to Orders</a>
    </div>
</div>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
    
    <!-- Customer Details Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-regular fa-user" style="margin-right: 8px;"></i> Customer Details</h2>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 8px;"><strong>Name:</strong> <?= e($order['customer_name'] ?: 'N/A') ?></p>
            <p style="margin-bottom: 8px;">
                <strong>Email:</strong> 
                <?php if ($order['customer_email']): ?>
                    <a href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a>
                <?php else: ?>
                    <span class="text-muted">Not provided</span>
                <?php endif; ?>
            </p>
            <p style="margin-bottom: 8px;">
                <strong>Phone:</strong> 
                <?php if ($order['customer_phone']): ?>
                    <a href="tel:<?= e($order['customer_phone']) ?>"><?= e($order['customer_phone']) ?></a>
                <?php else: ?>
                    <span class="text-muted">Not provided</span>
                <?php endif; ?>
            </p>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <strong style="display: block; margin-bottom: 8px;">Delivery Address:</strong>
                <div style="line-height: 1.6; color: var(--text-color);">
                    <?php
                        $addrStr = $order['delivery_address'];
                        $addrObj = json_decode($addrStr, true);
                        if (is_array($addrObj)) {
                            if (!empty($addrObj['address1'])) echo "<strong>Address:</strong> " . e($addrObj['address1']) . "<br>";
                            if (!empty($addrObj['address2'])) echo "<strong>Apartment/Suite:</strong> " . e($addrObj['address2']) . "<br>";
                            if (!empty($addrObj['city'])) echo "<strong>City:</strong> " . e($addrObj['city']) . "<br>";
                            if (!empty($addrObj['province'])) echo "<strong>Province:</strong> " . e($addrObj['province']) . "<br>";
                            if (!empty($addrObj['zip'])) echo "<strong>Postal Code:</strong> " . e($addrObj['zip']) . "<br>";
                            if (!empty($addrObj['country'])) echo "<strong>Country:</strong> " . e($addrObj['country']) . "<br>";
                        } else {
                            echo nl2br(e($addrStr ?: 'No address provided.'));
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Summary Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-receipt" style="margin-right: 8px;"></i> Order Summary</h2>
        </div>
        <div class="card-body">
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <strong>Assigned Branch:</strong>
                <span><?= $order['branch_name'] ? e($order['branch_name']) : '<span class="text-muted">Unassigned</span>' ?></span>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <strong>Order Status:</strong>
                <?= statusBadge($order['current_status']) ?>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <strong>Payment Status:</strong>
                <?php
                    $pClass = $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'failed' ? 'danger' : 'warning');
                ?>
                <span class="status <?= $pClass ?>"><?= ucfirst($order['payment_status']) ?></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <strong>Shopify Sync:</strong>
                <?php if ($order['sync_status'] === 'synced'): ?>
                    <span class="status completed"><i class="fa-solid fa-cloud-check"></i> Synced</span>
                <?php elseif ($order['sync_status'] === 'waiting'): ?>
                    <span class="status processing"><i class="fa-solid fa-cloud-arrow-up"></i> Waiting</span>
                <?php else: ?>
                    <span class="status inactive">Failed</span>
                <?php endif; ?>
            </div>

            <?php if ($order['shopify_order_number']): ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <strong>Shopify Order #:</strong>
                <span><?= e($order['shopify_order_number']) ?></span>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; font-size: 1.25rem;">
                <strong>Total Amount:</strong>
                <strong><?= get_setting($db, 'currency_symbol', 'SAR') . ' ' . number_format($order['total_amount'], 2) ?></strong>
            </div>
            
            <div style="margin-top: 16px; padding-top: 16px; display: flex; justify-content: flex-end;">
                <button class="btn btn-outline btn-update-status" 
                        data-id="<?= $order['id'] ?>"
                        data-number="<?= e($order['order_number']) ?>"
                        data-status="<?= e($order['current_status']) ?>"
                        data-payment="<?= e($order['payment_status']) ?>">
                    Update Status
                </button>
            </div>
        </div>
    </div>

</div>

<!-- Order Items -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-box-open" style="margin-right: 8px;"></i> Items Ordered</h2>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th class="text-right">Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5" class="empty-state" style="padding: 32px; text-align: center;">
                            <p>No items found for this order.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['item_name']) ?></strong>
                        </td>
                        <td class="text-muted"><?= e($item['sku'] ?: 'N/A') ?></td>
                        <td class="text-right"><?= get_setting($db, 'currency_symbol', 'SAR') . ' ' . number_format($item['unit_price'], 2) ?></td>
                        <td class="text-center">x<?= (int)$item['quantity'] ?></td>
                        <td class="text-right"><strong><?= get_setting($db, 'currency_symbol', 'SAR') . ' ' . number_format($item['subtotal'], 2) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- UPDATE STATUS MODAL -->
<div class="modal-overlay" id="modal-update-status" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="modal_order_id" value="">
            
            <div class="modal-header">
                <h3 class="modal-title">Update Order <span id="modal_order_number"></span></h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('modal-update-status')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="new_status">Order Status</label>
                    <select name="new_status" id="new_status" class="form-control" required>
                        <option value="New">New</option>
                        <option value="Assigned">Assigned</option>
                        <option value="Accepted">Accepted</option>
                        <option value="Preparing">Preparing</option>
                        <option value="Ready">Ready</option>
                        <option value="Out For Delivery">Out For Delivery</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Returned">Returned</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top:16px;">
                    <label for="new_payment_status">Payment Status</label>
                    <select name="new_payment_status" id="new_payment_status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-update-status')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Sync</button>
            </div>
        </form>
    </div>
</div>

<script>
// Open Status Modal
document.querySelectorAll('.btn-update-status').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('modal_order_id').value = this.dataset.id;
        document.getElementById('modal_order_number').innerText = this.dataset.number;
        document.getElementById('new_status').value = this.dataset.status;
        
        let paymentStatus = this.dataset.payment;
        const paymentSelect = document.getElementById('new_payment_status');
        if([...paymentSelect.options].some(o => o.value === paymentStatus)) {
            paymentSelect.value = paymentStatus;
        } else {
            paymentSelect.value = 'pending';
        }
        
        document.getElementById('modal-update-status').classList.add('show');
    });
});

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close on backdrop click
document.getElementById('modal-update-status').addEventListener('click', function(e) {
    if (e.target === this) closeModal('modal-update-status');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

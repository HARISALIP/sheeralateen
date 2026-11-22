<?php
/**
 * manager/inventory.php
 * ---------------------------------------------------------
 * Branch-Level Inventory Management for Branch Managers.
 * Allows setting exact stock, quick out-of-stock (0), and adjustment with reasons.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('branch_manager', '../login.php');

$db = Database::getConnection();
$branchId = (int) ($_SESSION['branch_id'] ?? 0);

if (!$branchId) {
    die('Access Denied: No branch assigned.');
}

// Fetch branch info
$bStmt = $db->prepare("SELECT branch_name, branch_code FROM branches WHERE id = :id");
$bStmt->execute([':id' => $branchId]);
$branch = $bStmt->fetch();

$inventoryService = new InventoryService($db);

// Handle POST Stock Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security token mismatch.');
        header('Location: inventory.php');
        exit;
    }

    $productId = (int) ($_POST['product_id'] ?? 0);
    $updateMode = $_POST['update_mode'] ?? 'set'; // 'set' or 'adjust'
    $qty = (int) ($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'manual_correction');
    $notes = trim($_POST['notes'] ?? '');
    $userId = (int) $_SESSION['user_id'];

    if ($productId > 0) {
        if ($updateMode === 'adjust') {
            $res = $inventoryService->adjustStock($branchId, $productId, $qty, $reason, $notes, $userId);
        } else {
            $res = $inventoryService->setStock($branchId, $productId, $qty, $reason, $notes, $userId);
        }

        if ($res['ok']) {
            flash('success', $res['message']);
        } else {
            flash('error', $res['message']);
        }
    }

    header('Location: inventory.php');
    exit;
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$inventory = $inventoryService->getBranchInventory($branchId, $search, $statusFilter);
$recentLogs = $inventoryService->getHistory($branchId, 15);

$pageTitle = 'Branch Inventory';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">Branch Inventory</h1>
        <p class="page-subtitle">Managing stock for: <strong><?= htmlspecialchars($branch['branch_name'] ?? 'Your Branch') ?></strong> (<?= htmlspecialchars($branch['branch_code'] ?? '') ?>)</p>
    </div>
</div>

<?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- FILTERS CARD -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" action="inventory.php" class="form-grid" style="align-items: end; display:flex; gap:12px; flex-wrap:wrap;">
            <div class="form-group" style="flex:2; min-width:200px;">
                <label for="search">Search Products</label>
                <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Product Name or SKU">
            </div>
            <div class="form-group" style="flex:1; min-width:160px;">
                <label for="status">Stock Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Items</option>
                    <option value="in_stock" <?= $statusFilter === 'in_stock' ? 'selected' : '' ?>>In Stock (> 0)</option>
                    <option value="low_stock" <?= $statusFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock (≤ 5)</option>
                    <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (0)</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="inventory.php" class="btn btn-outline">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- INVENTORY TABLE -->
<div class="card" style="margin-bottom: 32px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title">Product Stock List (<?= count($inventory) ?> Items)</h2>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 70px;">Image</th>
                    <th>Product Details</th>
                    <th>Price</th>
                    <th style="text-align:center;">Current Stock</th>
                    <th style="text-align:center;">Status</th>
                    <th class="text-right" style="min-width: 220px;">Quick Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($inventory)): ?>
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <p>No products found in catalog.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($inventory as $item): ?>
                <?php 
                    $qty = (int)$item['available_quantity'];
                    $badgeClass = $qty <= 0 ? 'inactive' : ($qty <= (int)$item['low_stock_threshold'] ? 'pending' : 'completed');
                    $badgeLabel = $qty <= 0 ? 'Out of Stock' : ($qty <= (int)$item['low_stock_threshold'] ? "Low Stock ($qty)" : "In Stock ($qty)");
                ?>
                <tr>
                    <td>
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid var(--border-color, #e2e8f0);">
                        <?php else: ?>
                            <div style="width:48px; height:48px; background:#f1f5f9; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($item['title']) ?></strong>
                        <?php if ($item['sku']): ?>
                            <br><small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>SAR <?= number_format((float)$item['price'], 2) ?></td>
                    <td style="text-align:center; font-size:16px; font-weight:700;">
                        <?= $qty ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="status <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                    </td>
                    <td class="text-right">
                        <div style="display:inline-flex; gap:6px; align-items:center;">
                            <!-- Set to 0 Quick Button -->
                            <?php if ($qty > 0): ?>
                            <form method="POST" action="inventory.php" style="display:inline; margin:0;" onsubmit="return confirm('Mark <?= htmlspecialchars(addslashes($item['title'])) ?> as Out of Stock (0)?');">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="update_stock">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <input type="hidden" name="update_mode" value="set">
                                <input type="hidden" name="quantity" value="0">
                                <input type="hidden" name="reason" value="Sold Out Today">
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#b91c1c; border:none; padding:4px 8px; font-size:12px; font-weight:600;" title="Set stock to 0 immediately">
                                    <i class="fa-solid fa-ban"></i> Set Out of Stock
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- Edit Stock Modal Trigger Button -->
                            <button type="button" class="btn btn-sm btn-outline btn-edit-stock" 
                                data-id="<?= $item['product_id'] ?>" 
                                data-title="<?= htmlspecialchars($item['title']) ?>" 
                                data-qty="<?= $qty ?>"
                                style="padding:4px 10px;">
                                <i class="fa-solid fa-pen-to-square"></i> Update Qty
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- AUDIT HISTORY SECTION -->
<div class="card" id="history-section">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Stock Change History (Your Branch)</h2>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Product</th>
                    <th>Change</th>
                    <th>Reason / Notes</th>
                    <th>Updated By</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentLogs)): ?>
                <tr>
                    <td colspan="5" class="empty-state">
                        <i class="fa-solid fa-history"></i>
                        <p>No inventory changes recorded yet.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentLogs as $log): ?>
                <?php 
                    $delta = (int)$log['delta'];
                    $deltaClass = $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-muted');
                    $deltaSign = $delta > 0 ? "+$delta" : "$delta";
                ?>
                <tr>
                    <td class="text-muted" style="white-space:nowrap; font-size:13px;">
                        <?= date('M d, Y h:i A', strtotime($log['created_at'])) ?>
                    </td>
                    <td><strong><?= htmlspecialchars($log['product_title']) ?></strong></td>
                    <td>
                        <span style="font-weight:600;"><?= $log['previous_quantity'] ?> → <?= $log['new_quantity'] ?></span>
                        <span class="<?= $deltaClass ?>" style="font-size:12px; margin-left:4px;">(<?= $deltaSign ?>)</span>
                    </td>
                    <td>
                        <span class="badge" style="background:#f1f5f9; color:#334155; padding:3px 8px; border-radius:4px; font-size:12px;">
                            <?= htmlspecialchars($log['reason']) ?>
                        </span>
                        <?php if (!empty($log['notes'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($log['notes']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- UPDATE STOCK MODAL -->
<div id="stockModal" style="display:none; position:fixed; z-index:9999; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:440px; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 id="modalTitle" style="margin-top:0; margin-bottom:16px;">Update Stock</h3>
        <form method="POST" action="inventory.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_stock">
            <input type="hidden" name="product_id" id="modalProductId" value="">

            <div class="form-group" style="margin-bottom:14px;">
                <label>Action Mode</label>
                <select name="update_mode" id="modalMode" class="form-control" onchange="toggleModeLabel()">
                    <option value="set">Set Exact Available Count</option>
                    <option value="adjust">Add (+) or Deduct (-) Stock</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label id="qtyLabel">New Quantity</label>
                <input type="number" name="quantity" id="modalQty" class="form-control" required min="0">
                <small class="text-muted" id="currentStockHint"></small>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label>Reason for Change <span style="color:#ef4444;">*</span></label>
                <select name="reason" class="form-control" required>
                    <option value="Received Daily Shipment">Received Daily Shipment (+)</option>
                    <option value="Sold Out / Finished">Sold Out / Finished (0)</option>
                    <option value="Damaged / Expired / Spoiled">Damaged / Expired / Spoiled (-)</option>
                    <option value="Physical Stock Correction">Physical Stock Correction</option>
                    <option value="Transfer to Other Branch">Transfer to Other Branch (-)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Optional Notes</label>
                <input type="text" name="notes" class="form-control" placeholder="e.g. Morning batch arrived from central kitchen">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('stockModal');
const modalTitle = document.getElementById('modalTitle');
const modalProductId = document.getElementById('modalProductId');
const modalQty = document.getElementById('modalQty');
const qtyLabel = document.getElementById('qtyLabel');
const modalMode = document.getElementById('modalMode');
const currentStockHint = document.getElementById('currentStockHint');

let activeCurrentQty = 0;

document.querySelectorAll('.btn-edit-stock').forEach(btn => {
    btn.addEventListener('click', function() {
        const pid = this.dataset.id;
        const title = this.dataset.title;
        activeCurrentQty = parseInt(this.dataset.qty) || 0;

        modalTitle.innerText = 'Update: ' + title;
        modalProductId.value = pid;
        modalMode.value = 'set';
        modalQty.value = activeCurrentQty;
        modalQty.min = 0;
        qtyLabel.innerText = 'Set Exact Quantity';
        currentStockHint.innerText = 'Current stock at your branch: ' + activeCurrentQty;

        modal.style.display = 'flex';
    });
});

function toggleModeLabel() {
    if (modalMode.value === 'adjust') {
        qtyLabel.innerText = 'Adjustment Units (+ to add, - to deduct)';
        modalQty.value = 5;
        modalQty.removeAttribute('min');
    } else {
        qtyLabel.innerText = 'Set Exact Quantity';
        modalQty.value = activeCurrentQty;
        modalQty.min = 0;
    }
}

function closeModal() {
    modal.style.display = 'none';
}

window.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

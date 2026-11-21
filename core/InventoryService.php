<?php
/**
 * core/InventoryService.php
 * ---------------------------------------------------------
 * Manages branch inventory, stock adjustments, audit logging,
 * and stock availability checks for orders.
 */
class InventoryService
{
    private PDO $db;
    private ?ShopifyService $shopify;

    public function __construct(PDO $db, ?ShopifyService $shopify = null)
    {
        $this->db = $db;
        $this->shopify = $shopify;
    }

    /**
     * Get inventory list for a specific branch.
     * Optionally filtered by search query or stock status.
     */
    public function getBranchInventory(int $branchId, string $search = '', string $statusFilter = ''): array
    {
        $sql = "
            SELECT 
                p.id AS product_id,
                p.handle,
                p.title,
                p.sku,
                p.price,
                p.image_url,
                p.status AS product_status,
                COALESCE(bi.available_quantity, 0) AS available_quantity,
                COALESCE(bi.on_hand_quantity, 0) AS on_hand_quantity,
                COALESCE(bi.low_stock_threshold, 5) AS low_stock_threshold,
                bi.updated_at AS last_updated
            FROM products_catalog p
            LEFT JOIN branch_inventory bi 
                ON p.id = bi.product_id AND bi.branch_id = :branch_id
            WHERE p.status = 'active'
        ";

        $params = [':branch_id' => $branchId];

        if ($search !== '') {
            $sql .= " AND (p.title LIKE :search OR p.handle LIKE :search OR p.sku LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($statusFilter === 'in_stock') {
            $sql .= " AND COALESCE(bi.available_quantity, 0) > 0";
        } elseif ($statusFilter === 'out_of_stock') {
            $sql .= " AND COALESCE(bi.available_quantity, 0) <= 0";
        } elseif ($statusFilter === 'low_stock') {
            $sql .= " AND COALESCE(bi.available_quantity, 0) > 0 AND COALESCE(bi.available_quantity, 0) <= COALESCE(bi.low_stock_threshold, 5)";
        }

        $sql .= " ORDER BY p.title ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Set exact available stock for a product at a branch.
     */
    public function setStock(
        int $branchId,
        int $productId,
        int $newQuantity,
        string $reason,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        if ($newQuantity < 0) {
            return ['ok' => false, 'message' => 'Quantity cannot be negative.'];
        }

        // Fetch current quantity
        $stmt = $this->db->prepare("
            SELECT available_quantity 
            FROM branch_inventory 
            WHERE branch_id = :bid AND product_id = :pid 
            LIMIT 1
        ");
        $stmt->execute([':bid' => $branchId, ':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $previousQty = $row !== false ? (int)$row['available_quantity'] : 0;
        $delta = $newQuantity - $previousQty;

        if ($previousQty === $newQuantity && $row !== false) {
            return ['ok' => true, 'message' => 'Quantity is already set to ' . $newQuantity, 'current_qty' => $newQuantity];
        }

        $this->db->beginTransaction();
        try {
            // Upsert branch_inventory
            $upsert = $this->db->prepare("
                INSERT INTO branch_inventory (branch_id, product_id, available_quantity, on_hand_quantity, updated_at)
                VALUES (:bid, :pid, :qty, :qty, NOW())
                ON DUPLICATE KEY UPDATE 
                    available_quantity = :qty_up,
                    on_hand_quantity = :qty_up2,
                    updated_at = NOW()
            ");
            $upsert->execute([
                ':bid' => $branchId,
                ':pid' => $productId,
                ':qty' => $newQuantity,
                ':qty_up' => $newQuantity,
                ':qty_up2' => $newQuantity
            ]);

            // Insert audit log
            $log = $this->db->prepare("
                INSERT INTO inventory_logs (
                    branch_id, product_id, previous_quantity, new_quantity,
                    delta, adjustment_type, reason, notes, user_id, sync_status, created_at
                ) VALUES (
                    :bid, :pid, :prev, :new,
                    :delta, 'set', :reason, :notes, :uid, 'local_only', NOW()
                )
            ");
            $log->execute([
                ':bid'     => $branchId,
                ':pid'     => $productId,
                ':prev'    => $previousQty,
                ':new'     => $newQuantity,
                ':delta'   => $delta,
                ':reason'  => $reason,
                ':notes'   => $notes,
                ':uid'     => $userId
            ]);

            $this->db->commit();

            ActivityLogger::log(
                $userId,
                'inventory_updated',
                "Stock set to {$newQuantity} (was {$previousQty}, reason: {$reason})",
                $branchId
            );

            return [
                'ok' => true,
                'previous_qty' => $previousQty,
                'new_qty' => $newQuantity,
                'delta' => $delta,
                'message' => "Stock successfully updated to {$newQuantity}."
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['ok' => false, 'message' => 'Failed to update stock: ' . $e->getMessage()];
        }
    }

    /**
     * Adjust stock by adding or deducting a delta (+/-).
     */
    public function adjustStock(
        int $branchId,
        int $productId,
        int $delta,
        string $reason,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        if ($delta === 0) {
            return ['ok' => false, 'message' => 'Delta adjustment cannot be zero.'];
        }

        // Fetch current quantity
        $stmt = $this->db->prepare("
            SELECT available_quantity 
            FROM branch_inventory 
            WHERE branch_id = :bid AND product_id = :pid 
            LIMIT 1
        ");
        $stmt->execute([':bid' => $branchId, ':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $previousQty = $row !== false ? (int)$row['available_quantity'] : 0;
        $newQuantity = max(0, $previousQty + $delta);

        return $this->setStock($branchId, $productId, $newQuantity, $reason, $notes, $userId);
    }

    /**
     * Get recent audit history logs for a branch or all branches.
     */
    public function getHistory(?int $branchId = null, int $limit = 50): array
    {
        $sql = "
            SELECT 
                il.*,
                p.title AS product_title,
                p.sku AS product_sku,
                p.image_url,
                b.branch_name,
                u.name AS user_name
            FROM inventory_logs il
            JOIN products_catalog p ON il.product_id = p.id
            JOIN branches b ON il.branch_id = b.id
            LEFT JOIN users u ON il.user_id = u.id
        ";

        $params = [];
        if ($branchId !== null) {
            $sql .= " WHERE il.branch_id = :branch_id";
            $params[':branch_id'] = $branchId;
        }

        $sql .= " ORDER BY il.created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if all items in an order are in stock at the assigned branch.
     * If out of stock, identify the next nearest branch that HAS stock.
     */
    public function checkOrderStockStatus(int $orderId, int $branchId): array
    {
        // 1. Fetch order items
        $stmt = $this->db->prepare("
            SELECT oi.id, oi.item_name, oi.quantity, oi.sku
            FROM order_items oi
            WHERE oi.order_id = :oid
        ");
        $stmt->execute([':oid' => $orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasOutOfStock = false;
        $outOfStockItems = [];

        foreach ($items as $item) {
            // Match against products_catalog by title or SKU
            $chk = $this->db->prepare("
                SELECT p.id, p.title, COALESCE(bi.available_quantity, 0) AS stock
                FROM products_catalog p
                LEFT JOIN branch_inventory bi ON p.id = bi.product_id AND bi.branch_id = :bid
                WHERE p.title LIKE :name OR (p.sku IS NOT NULL AND p.sku = :sku)
                LIMIT 1
            ");
            $cleanName = trim(explode(' - ', $item['item_name'])[0]);
            $chk->execute([
                ':bid'  => $branchId,
                ':name' => '%' . $cleanName . '%',
                ':sku'  => $item['sku'] ?? ''
            ]);
            $match = $chk->fetch(PDO::FETCH_ASSOC);

            if ($match) {
                $available = (int)$match['stock'];
                if ($available < (int)$item['quantity']) {
                    $hasOutOfStock = true;
                    $outOfStockItems[] = [
                        'item_name' => $item['item_name'],
                        'required'  => (int)$item['quantity'],
                        'available' => $available,
                        'product_id'=> $match['id']
                    ];
                }
            }
        }

        // 2. If out of stock, find alternative branches with stock
        $alternativeBranch = null;
        if ($hasOutOfStock && !empty($outOfStockItems)) {
            // Find branches that have stock for these items
            $firstPid = $outOfStockItems[0]['product_id'];
            $altStmt = $this->db->prepare("
                SELECT b.id, b.branch_name, b.phone, bi.available_quantity
                FROM branch_inventory bi
                JOIN branches b ON bi.branch_id = b.id
                WHERE bi.product_id = :pid 
                  AND bi.available_quantity >= :req
                  AND b.id != :bid
                  AND b.status = 'active'
                  AND b.deleted_at IS NULL
                ORDER BY bi.available_quantity DESC
                LIMIT 1
            ");
            $altStmt->execute([
                ':pid' => $firstPid,
                ':req' => $outOfStockItems[0]['required'],
                ':bid' => $branchId
            ]);
            $alternativeBranch = $altStmt->fetch(PDO::FETCH_ASSOC);
        }

        return [
            'all_in_stock'       => !$hasOutOfStock,
            'out_of_stock_items' => $outOfStockItems,
            'suggested_branch'   => $alternativeBranch
        ];
    }
}

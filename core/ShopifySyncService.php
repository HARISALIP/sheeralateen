<?php
/**
 * core/ShopifySyncService.php
 * ---------------------------------------------------------
 * Orchestrates two-way synchronization between local DB and Shopify.
 * Separates business logic from raw API calls.
 */
class ShopifySyncService
{
    private PDO $db;
    private ShopifyService $api;
    private SyncQueue $queue;
    
    private ?array $activeBranches = null;

    public function __construct(PDO $db, ShopifyService $api, SyncQueue $queue)
    {
        $this->db = $db;
        $this->api = $api;
        $this->queue = $queue;
    }

    /**
     * Helper: Fetch and cache active branches with their Shopify Location IDs
     */
    private function getActiveBranches(): array
    {
        if ($this->activeBranches !== null) {
            return $this->activeBranches;
        }

        $this->activeBranches = [];
        $stmt = $this->db->query("SELECT id, shopify_location_id FROM branches WHERE status = 'active'");
        while ($row = $stmt->fetch()) {
            $this->activeBranches[] = [
                'id' => (int)$row['id'],
                'shopify_location_id' => $row['shopify_location_id'] ? (int)$row['shopify_location_id'] : null
            ];
        }
        return $this->activeBranches;
    }

    /**
     * Import new or updated orders from Shopify.
     * Shopify is the source of truth for order creation, customers, and payments.
     */
    public function importOrders(): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'errors' => []];

        try {
            // Get the last successful sync time to only fetch new/updated orders
            $lastSyncStr = get_setting($this->db, 'shopify_last_sync_time', '');
            $params = [
                'status' => 'any',
                'limit' => 50, // Process in batches to avoid timeouts
                'fields' => 'id,name,created_at,updated_at,financial_status,fulfillment_status,total_price,customer,shipping_address,note_attributes'
            ];

            if ($lastSyncStr) {
                // Fetch anything updated since last successful sync minus 5 minutes buffer
                $lastSync = new DateTime($lastSyncStr);
                $lastSync->modify('-5 minutes'); 
                $params['updated_at_min'] = $lastSync->format(DateTime::ATOM);
            }

            $response = $this->api->getOrders($params);
            $orders = $response['orders'] ?? [];

            foreach ($orders as $shopifyOrder) {
                try {
                    $result = $this->importSingleOrder($shopifyOrder);
                    if ($result === 'imported') $stats['imported']++;
                    if ($result === 'updated') $stats['updated']++;
                } catch (Exception $e) {
                    $stats['errors'][] = "Order #{$shopifyOrder['name']}: " . $e->getMessage();
                }
            }

            // Update last sync time on success
            if (empty($stats['errors']) || count($orders) > 0) {
                save_setting($this->db, 'shopify_last_sync_time', (new DateTime())->format('Y-m-d H:i:s'));
                save_setting($this->db, 'shopify_last_sync_status', 'Healthy');
            } else if (!empty($stats['errors'])) {
                save_setting($this->db, 'shopify_last_sync_status', 'Warning');
            }

        } catch (Exception $e) {
            $stats['errors'][] = "Sync failed: " . $e->getMessage();
            save_setting($this->db, 'shopify_last_sync_status', 'Error');
            save_setting($this->db, 'shopify_last_failed_sync', (new DateTime())->format('Y-m-d H:i:s'));
            ActivityLogger::log(null, 'shopify_sync_failed', $e->getMessage());
        }

        if ($stats['imported'] > 0 || $stats['updated'] > 0) {
            ActivityLogger::log(null, 'shopify_sync_success', "Imported: {$stats['imported']}, Updated: {$stats['updated']}");
        }

        return $stats;
    }

    /**
     * Import or update a single order from Shopify.
     * Returns 'imported', 'updated', or 'skipped'.
     */
    public function importSingleOrder(array $shopifyOrder): string
    {
        $shopifyId = (string) $shopifyOrder['id'];
        $orderNumber = $shopifyOrder['name']; // e.g. "#1001"
        $total = (float) $shopifyOrder['total_price'];
        $financialStatus = $shopifyOrder['financial_status'] ?? 'pending';
        $fulfillmentStatus = $shopifyOrder['fulfillment_status'] ?? 'unfulfilled';

        $customerName = '';
        $customerEmail = '';
        $customerPhone = '';
        if (isset($shopifyOrder['customer'])) {
            $customerName = trim(($shopifyOrder['customer']['first_name'] ?? '') . ' ' . ($shopifyOrder['customer']['last_name'] ?? ''));
            $customerEmail = $shopifyOrder['customer']['email'] ?? '';
            $customerPhone = $shopifyOrder['customer']['phone'] ?? '';
        }
        
        $deliveryAddress = '';
        $deliveryAddressPlain = '';
        if (isset($shopifyOrder['shipping_address'])) {
            $addr = $shopifyOrder['shipping_address'];
            $addressObj = [
                'address1' => $addr['address1'] ?? '',
                'address2' => $addr['address2'] ?? '',
                'city' => $addr['city'] ?? '',
                'province' => $addr['province'] ?? '',
                'zip' => $addr['zip'] ?? '',
                'country' => $addr['country'] ?? ''
            ];
            $deliveryAddress = json_encode($addressObj, JSON_UNESCAPED_UNICODE);
            
            $parts = array_filter(array_values($addressObj));
            $deliveryAddressPlain = implode(', ', $parts);
            // Fallback phone from shipping address if customer phone is empty
            if (empty($customerPhone) && !empty($addr['phone'])) {
                $customerPhone = $addr['phone'];
            }
        }

        // Map Shopify financial status to local payment_status enum
        $localPaymentStatus = 'pending';
        if (in_array($financialStatus, ['paid', 'partially_refunded'])) {
            $localPaymentStatus = 'paid';
        } elseif (in_array($financialStatus, ['refunded', 'voided'])) {
            $localPaymentStatus = 'refunded';
        }
        
        $assignedBranchId = null;

        // Fetch fulfillment orders to find the assigned pickup location
        try {
            $foResponse = $this->api->getOrderFulfillmentOrders($shopifyId);
            $fulfillmentOrders = $foResponse['fulfillment_orders'] ?? [];
            if (!empty($fulfillmentOrders) && isset($fulfillmentOrders[0]['assigned_location_id'])) {
                $locationId = (int)$fulfillmentOrders[0]['assigned_location_id'];
                
                // Find matching branch
                foreach ($this->getActiveBranches() as $branch) {
                    if ($branch['shopify_location_id'] === $locationId) {
                        $assignedBranchId = $branch['id'];
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // Log error if unable to fetch fulfillment orders
            ActivityLogger::log(null, 'shopify_sync_warning', "Could not fetch fulfillment orders for {$orderNumber}: " . $e->getMessage());
        }

        // Fallback: Check note_attributes for cart attributes injected by the store pickup widget
        if (!$assignedBranchId && isset($shopifyOrder['note_attributes']) && is_array($shopifyOrder['note_attributes'])) {
            $branchCode = null;
            foreach ($shopifyOrder['note_attributes'] as $attr) {
                if (isset($attr['name']) && $attr['name'] === 'Pickup_Store_ID') {
                    $branchCode = $attr['value'];
                    break;
                }
            }
            
            if ($branchCode) {
                $stmtAttr = $this->db->prepare("SELECT id FROM branches WHERE branch_code = :code1 OR branch_name = :code2 LIMIT 1");
                $stmtAttr->execute([':code1' => $branchCode, ':code2' => $branchCode]);
                $branchRow = $stmtAttr->fetch();
                if ($branchRow) {
                    $assignedBranchId = $branchRow['id'];
                }
            }
        }

        // Check if order already exists
        $stmt = $this->db->prepare("SELECT id, shopify_financial_status, shopify_fulfillment_status FROM orders WHERE shopify_order_id = :sid OR order_number = :onum LIMIT 1");
        $stmt->execute([':sid' => $shopifyId, ':onum' => $orderNumber]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing order IF Shopify data changed
            if ($existing['shopify_financial_status'] !== $financialStatus || 
                $existing['shopify_fulfillment_status'] !== $fulfillmentStatus) {
                
                $updateStmt = $this->db->prepare("
                    UPDATE orders 
                    SET customer_name = :cname, 
                        customer_email = :cemail, 
                        customer_phone = :cphone,
                        delivery_address = :address,
                        total_amount = :total,
                        payment_status = :pstatus,
                        shopify_financial_status = :s_financial,
                        shopify_fulfillment_status = :s_fulfillment,
                        shopify_synced_at = NOW(),
                        sync_source = 1,
                        assigned_branch_id = COALESCE(:branch_id, assigned_branch_id)
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    ':cname' => $customerName ?: 'Unknown',
                    ':cemail' => $customerEmail,
                    ':cphone' => $customerPhone,
                    ':address' => $deliveryAddress,
                    ':total' => $total,
                    ':pstatus' => $localPaymentStatus,
                    ':s_financial' => $financialStatus,
                    ':s_fulfillment' => $fulfillmentStatus,
                    ':branch_id' => $assignedBranchId,
                    ':id' => $existing['id']
                ]);
                
                if (isset($shopifyOrder['line_items'])) {
                    $this->syncOrderItems($existing['id'], $shopifyOrder['line_items']);
                }
                
                return 'updated';
            }
            return 'skipped';
        }

        $createdAt = isset($shopifyOrder['created_at']) ? date('Y-m-d H:i:s', strtotime($shopifyOrder['created_at'])) : date('Y-m-d H:i:s');

        // Insert new order
        $insertStmt = $this->db->prepare("
            INSERT INTO orders (
                shopify_order_id, shopify_order_number, order_number, 
                customer_name, customer_email, customer_phone, delivery_address,
                total_amount, payment_status, shopify_financial_status, shopify_fulfillment_status,
                sync_source, sync_status, shopify_synced_at, current_status, assigned_branch_id, created_at
            ) VALUES (
                :sid, :snum, :onum,
                :cname, :cemail, :cphone, :address,
                :total, :pstatus, :s_financial, :s_fulfillment,
                1, 'synced', NOW(), 'New', :branch_id, :created_at
            )
        ");
        $insertStmt->execute([
            ':sid' => $shopifyId,
            ':snum' => $orderNumber,
            ':onum' => $orderNumber,
            ':cname' => $customerName ?: 'Unknown',
            ':cemail' => $customerEmail,
            ':cphone' => $customerPhone,
            ':address' => $deliveryAddress,
            ':total' => $total,
            ':pstatus' => $localPaymentStatus,
            ':s_financial' => $financialStatus,
            ':s_fulfillment' => $fulfillmentStatus,
            ':branch_id' => $assignedBranchId,
            ':created_at' => $createdAt
        ]);

        $newOrderId = (int) $this->db->lastInsertId();
        if (isset($shopifyOrder['line_items'])) {
            $this->syncOrderItems($newOrderId, $shopifyOrder['line_items']);
        }

        return 'imported';
    }

    /**
     * Sync line items for a specific local order.
     */
    private function syncOrderItems(int $orderId, array $lineItems): void
    {
        // First delete existing items for this order to ensure fresh state
        $delStmt = $this->db->prepare("DELETE FROM order_items WHERE order_id = :oid");
        $delStmt->execute([':oid' => $orderId]);

        if (empty($lineItems)) return;

        $insert = $this->db->prepare("
            INSERT INTO order_items (order_id, item_name, quantity, unit_price, subtotal)
            VALUES (:oid, :name, :qty, :price, :subtotal)
        ");

        foreach ($lineItems as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            
            // Shopify variations usually look like: "Shirt - Red / Large"
            $itemName = $item['title'] ?? 'Unknown Item';
            if (!empty($item['variant_title'])) {
                $itemName .= ' - ' . $item['variant_title'];
            }

            $insert->execute([
                ':oid' => $orderId,
                ':name' => $itemName,
                ':qty' => $qty,
                ':price' => $price,
                ':subtotal' => $qty * $price
            ]);
        }
    }

    /**
     * Process the sync queue (Pushing local changes back to Shopify).
     */
    public function processQueue(int $limit = 20): array
    {
        $stats = ['processed' => 0, 'failed' => 0];
        
        if (!$this->api->isConfigured()) {
            return $stats; // Cannot process without API configured
        }

        $jobs = $this->queue->getAndLockPending($limit);

        foreach ($jobs as $job) {
            try {
                if ($job['entity_type'] === 'order') {
                    $this->processOrderJob($job);
                } else {
                    throw new Exception("Unknown entity_type: " . $job['entity_type']);
                }

                $this->queue->markCompleted($job['id']);
                $stats['processed']++;

            } catch (Exception $e) {
                $this->queue->markFailed($job['id'], $e->getMessage());
                $stats['failed']++;
                ActivityLogger::log(null, 'sync_queue_failed', "Job #{$job['id']} failed: " . $e->getMessage(), null, $job['local_entity_id']);
            }
        }

        return $stats;
    }

    /**
     * Handle a single order push job.
     */
    private function processOrderJob(array $job): void
    {
        $localOrderId = $job['local_entity_id'];
        $shopifyOrderId = $job['remote_entity_id'];
        
        if (!$shopifyOrderId) {
            // We can't push to Shopify if we don't know the Shopify ID.
            // Mark as completed to avoid retry loops, but log a warning.
            throw new Exception("Missing remote_entity_id for local order #$localOrderId");
        }

        // Get the current local order status
        $stmt = $this->db->prepare("SELECT current_status, payment_status, sync_source FROM orders WHERE id = :id");
        $stmt->execute([':id' => $localOrderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("Local order not found: $localOrderId");
        }

        // Loop Prevention: 
        // If this exact change was triggered by Shopify (sync_source=1), 
        // don't echo it back immediately.
        // Wait, current_status is local only, so we DO push it.
        // But let's say we update Tags based on current_status.
        
        $status = $order['current_status'];
        $tags = "Status: " . $status;

        // Action: push_status -> update tags
        if ($job['action'] === 'push_status') {
            $this->api->updateOrderTags($shopifyOrderId, $tags);
            
            // If Delivered, attempt Fulfillment
            if ($status === 'Delivered') {
                try {
                    $this->api->createFulfillment($shopifyOrderId);
                } catch (Exception $e) {
                    // It might already be fulfilled, or no items. We catch and ignore or rethrow.
                    // For now, if fulfillment fails (e.g. already fulfilled), we ignore it and just log it.
                    error_log("Fulfillment skip: " . $e->getMessage());
                }
            }
            
            // If Cancelled, actually cancel in Shopify
            if ($status === 'Cancelled') {
                try {
                    $this->api->cancelOrder($shopifyOrderId, true);
                } catch (Exception $e) {
                    error_log("Cancellation skip: " . $e->getMessage());
                }
            }
        }
        
        // Action: push_payment -> create transaction
        if ($job['action'] === 'push_payment') {
            if ($order['payment_status'] === 'paid') {
                try {
                    $this->api->createTransaction($shopifyOrderId, 'sale');
                } catch (Exception $e) {
                    error_log("Transaction skip: " . $e->getMessage());
                }
            }
        }
    }
}

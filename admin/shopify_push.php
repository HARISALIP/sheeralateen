<?php
/**
 * admin/shopify_push.php
 * ---------------------------------------------------------
 * AJAX endpoint to explicitly enqueue a push job for a specific order.
 * E.g., when a "Retry Sync" button is clicked for a failed order.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'message' => 'Security token mismatch.']);
    exit;
}

$orderId = (int) ($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid order ID.']);
    exit;
}

try {
    $db = Database::getConnection();
    
    // Get the order's Shopify ID
    $stmt = $db->prepare("SELECT shopify_order_id FROM orders WHERE id = :id");
    $stmt->execute([':id' => $orderId]);
    $shopifyOrderId = $stmt->fetchColumn();
    
    if (!$shopifyOrderId) {
        throw new Exception("This order is not linked to Shopify.");
    }
    
    $queue = new SyncQueue($db);
    $queue->enqueue('order', $orderId, $shopifyOrderId, 'push_status');
    
    echo json_encode(['ok' => true, 'message' => 'Sync job enqueued successfully.']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

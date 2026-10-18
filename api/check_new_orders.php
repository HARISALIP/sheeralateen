<?php
/**
 * api/check_new_orders.php
 * -------------------------------------------------------------
 * Lightweight endpoint to check for newly arrived orders.
 * Returns new orders for Super Admin (all branches) or
 * Branch Manager (assigned branch only).
 */
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db       = Database::getConnection();
$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$sinceId  = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

// Initial handshake: if since_id <= 0, return current max order ID so we don't alert old orders
if ($sinceId <= 0) {
    if ($role === 'super_admin') {
        $stmt = $db->query("SELECT COALESCE(MAX(id), 0) FROM orders WHERE deleted_at IS NULL");
    } else {
        $stmt = $db->prepare("SELECT COALESCE(MAX(id), 0) FROM orders WHERE assigned_branch_id = :bid AND deleted_at IS NULL");
        $stmt->execute([':bid' => $branchId]);
    }
    $maxId = (int)$stmt->fetchColumn();
    echo json_encode([
        'initialized' => true,
        'latest_id'   => $maxId,
        'orders'      => []
    ]);
    exit;
}

// Fetch new orders with ID > since_id
if ($role === 'super_admin') {
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.total_amount, o.created_at, b.branch_name
        FROM orders o
        LEFT JOIN branches b ON o.assigned_branch_id = b.id
        WHERE o.id > :since AND o.deleted_at IS NULL
        ORDER BY o.id ASC
        LIMIT 10
    ");
    $stmt->execute([':since' => $sinceId]);
} else {
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.total_amount, o.created_at, b.branch_name
        FROM orders o
        LEFT JOIN branches b ON o.assigned_branch_id = b.id
        WHERE o.id > :since AND o.assigned_branch_id = :bid AND o.deleted_at IS NULL
        ORDER BY o.id ASC
        LIMIT 10
    ");
    $stmt->execute([':since' => $sinceId, ':bid' => $branchId]);
}

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$maxId  = $sinceId;
foreach ($orders as $o) {
    if ((int)$o['id'] > $maxId) {
        $maxId = (int)$o['id'];
    }
}

echo json_encode([
    'initialized' => false,
    'latest_id'   => $maxId,
    'has_new'     => count($orders) > 0,
    'orders'      => $orders
]);

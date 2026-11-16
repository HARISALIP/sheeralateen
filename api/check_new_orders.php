<?php
/**
 * api/check_new_orders.php
 * -------------------------------------------------------------
 * Lightweight endpoint to check for newly arrived orders and
 * unacknowledged pending orders needing reminders.
 * Returns data for Super Admin (all branches) or Branch Manager (assigned branch).
 */

// Strip any BOM or whitespace that may have been output before <?php
while (ob_get_level() > 0) { ob_end_clean(); }
ob_start();

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

// -------------------------------------------------------------
// Check for Unacknowledged Pending Orders (Reminders)
// Orders still in 'New' status after 2 minutes (up to 3 hours)
// -------------------------------------------------------------
$pendingReminders = [];
try {
    $remindSql = "
        SELECT o.id, o.order_number, o.customer_name, o.total_amount, o.created_at, b.branch_name,
               TIMESTAMPDIFF(SECOND, o.created_at, NOW()) AS age_seconds
        FROM orders o
        LEFT JOIN branches b ON o.assigned_branch_id = b.id
        WHERE o.current_status = 'New'
          AND o.deleted_at IS NULL
          AND o.created_at <= (NOW() - INTERVAL 2 MINUTE)
          AND o.created_at >= (NOW() - INTERVAL 3 HOUR)
    ";

    if ($role === 'super_admin') {
        $stmtRemind = $db->query($remindSql . " ORDER BY o.created_at ASC LIMIT 5");
        $pendingReminders = $stmtRemind->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtRemind = $db->prepare($remindSql . " AND o.assigned_branch_id = :bid ORDER BY o.created_at ASC LIMIT 5");
        $stmtRemind->execute([':bid' => $branchId]);
        $pendingReminders = $stmtRemind->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    error_log('check_new_orders reminder query error: ' . $e->getMessage());
}

foreach ($pendingReminders as &$pr) {
    $pr['age_minutes'] = max(1, (int) round(($pr['age_seconds'] ?? 0) / 60));
}
unset($pr);

// -------------------------------------------------------------
// Handshake: if since_id <= 0, check for recent orders created in the last 15 minutes
// -------------------------------------------------------------
if ($sinceId <= 0) {
    if ($role === 'super_admin') {
        $stmtMax = $db->query("SELECT COALESCE(MAX(id), 0) FROM orders WHERE deleted_at IS NULL");
        $maxId = (int)$stmtMax->fetchColumn();

        $stmtRecent = $db->query("
            SELECT o.id, o.order_number, o.customer_name, o.total_amount, o.created_at, b.branch_name
            FROM orders o
            LEFT JOIN branches b ON o.assigned_branch_id = b.id
            WHERE o.created_at >= (NOW() - INTERVAL 15 MINUTE) AND o.current_status = 'New' AND o.deleted_at IS NULL
            ORDER BY o.id ASC
            LIMIT 5
        ");
        $recentOrders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtMax = $db->prepare("SELECT COALESCE(MAX(id), 0) FROM orders WHERE assigned_branch_id = :bid AND deleted_at IS NULL");
        $stmtMax->execute([':bid' => $branchId]);
        $maxId = (int)$stmtMax->fetchColumn();

        $stmtRecent = $db->prepare("
            SELECT o.id, o.order_number, o.customer_name, o.total_amount, o.created_at, b.branch_name
            FROM orders o
            LEFT JOIN branches b ON o.assigned_branch_id = b.id
            WHERE o.assigned_branch_id = :bid AND o.created_at >= (NOW() - INTERVAL 15 MINUTE) AND o.current_status = 'New' AND o.deleted_at IS NULL
            ORDER BY o.id ASC
            LIMIT 5
        ");
        $stmtRecent->execute([':bid' => $branchId]);
        $recentOrders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'initialized' => true,
        'latest_id'   => $maxId,
        'has_new'     => count($recentOrders) > 0,
        'orders'      => $recentOrders,
        'reminders'   => $pendingReminders
    ]);
    exit;
}

// -------------------------------------------------------------
// Regular polling: fetch new orders with ID > since_id
// -------------------------------------------------------------
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
    'orders'      => $orders,
    'reminders'   => $pendingReminders
]);

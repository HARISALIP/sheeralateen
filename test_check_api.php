<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
header('Content-Type: text/plain');

// Simulate super_admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';

// Test since_id = 9
$sinceId = 9;
$stmt = $db->prepare("
    SELECT o.id, o.order_number, o.customer_name, o.total_amount, o.created_at, b.branch_name
    FROM orders o
    LEFT JOIN branches b ON o.assigned_branch_id = b.id
    WHERE o.id > :since AND o.deleted_at IS NULL
    ORDER BY o.id ASC
    LIMIT 10
");
$stmt->execute([':since' => $sinceId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Query result for since_id = 9:\n";
print_r($orders);

// Test since_id = 0
echo "\nTest since_id = 0 response from check_new_orders.php:\n";
$_GET['since_id'] = 0;
ob_start();
include __DIR__ . '/api/check_new_orders.php';
$res = ob_get_clean();
echo $res;

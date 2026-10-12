<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== ORDER 1121 ===\n";
$stmt = $db->query("SELECT o.id, o.order_number, o.current_status, o.assigned_branch_id, b.branch_name, b.leajlak_shop_id, o.delivery_address FROM orders o LEFT JOIN branches b ON o.assigned_branch_id = b.id WHERE o.order_number = '#1121' OR o.order_number = '1121'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ALL BRANCHES LEAJLAK SHOP IDS ===\n";
$stmt = $db->query("SELECT id, branch_name, leajlak_shop_id FROM branches");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ACTIVITY LOGS (RECENT) ===\n";
$stmt = $db->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 20");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

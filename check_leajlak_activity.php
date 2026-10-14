<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== LEAJLAK ACTIVITY LOGS ===\n";
$stmt = $db->query("SELECT id, user_id, order_id, action, description, created_at FROM activity_logs WHERE action LIKE '%leajlak%' ORDER BY id DESC LIMIT 20");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== RECENT ORDERS WITH LEAJLAK_ORDER_ID ===\n";
$stmt2 = $db->query("SELECT id, order_number, current_status, leajlak_status, leajlak_order_id, updated_at FROM orders WHERE order_number IN ('#1124', '#1125', '#1126', '1124', '1125', '1126') OR id > 100 ORDER BY id DESC LIMIT 10");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== RECENT WEBHOOK LOGS ===\n";
$stmt3 = $db->query("SELECT id, topic, payload, processed, received_at FROM webhook_logs ORDER BY id DESC LIMIT 5");
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));

<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

echo "=== ORDER #1125 ROW ===\n";
$stmt = $db->query("SELECT id, order_number, current_status, leajlak_status, leajlak_order_id, leajlak_captain_name, updated_at FROM orders WHERE order_number LIKE '%1125%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== WEBHOOK LOGS FOR #1125 ===\n";
$stmt = $db->query("SELECT id, payload, received_at FROM webhook_logs WHERE payload LIKE '%1125%' ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ACTIVITY LOGS FOR #1125 ===\n";
$stmt = $db->query("SELECT id, action, description, created_at FROM activity_logs WHERE description LIKE '%1125%' ORDER BY id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

echo "=== RECENT ORDERS WITH LEAJLAK INFO ===\n";
$stmt = $db->query("SELECT id, order_number, current_status, leajlak_status, leajlak_order_id, assigned_branch_id FROM orders ORDER BY id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== RECENT ACTIVITY LOGS ===\n";
$stmt = $db->query("SELECT id, user_id, order_id, action, description, created_at FROM activity_logs ORDER BY id DESC LIMIT 20");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

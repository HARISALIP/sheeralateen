<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== ORDER ROW #4 ===\n";
$stmt = $db->query("SELECT * FROM orders WHERE id = 4");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== WEBHOOK LOGS FOR #1130 ===\n";
$stmt2 = $db->query("SELECT id, topic, payload, received_at FROM webhook_logs WHERE payload LIKE '%1130%' ORDER BY id ASC");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ORDER STATUS HISTORY FOR #4 ===\n";
$stmt3 = $db->query("SELECT * FROM order_status_history WHERE order_id = 4 ORDER BY id ASC");
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));

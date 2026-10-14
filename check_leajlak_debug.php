<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== LEAJLAK ACTIVITY LOGS ===\n";
$stmt = $db->query("SELECT * FROM activity_logs WHERE action LIKE '%leajlak%' ORDER BY id DESC LIMIT 30");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['id']} | Action: {$row['action']} | OrderID: {$row['order_id']} | Desc: {$row['description']}\n";
}

echo "\n=== RECENT ORDERS ===\n";
$stmt2 = $db->query("SELECT id, order_number, current_status, leajlak_status, leajlak_order_id FROM orders ORDER BY id DESC LIMIT 10");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['id']} | Order: {$row['order_number']} | Status: {$row['current_status']} | L_Status: {$row['leajlak_status']} | L_ID: {$row['leajlak_order_id']}\n";
}

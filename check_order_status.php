<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$stmt = $db->query("SELECT * FROM orders WHERE id = 17 OR order_number LIKE '%1147%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- ACTIVITY LOGS ---\n";
$stmt2 = $db->query("SELECT * FROM activity_logs WHERE order_id = 17 OR description LIKE '%1147%' ORDER BY id DESC LIMIT 10");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- BRANCHES ---\n";
$stmt3 = $db->query("SELECT id, branch_name, branch_code, leajlak_shop_id FROM branches WHERE branch_name LIKE '%Harasath%' OR branch_name LIKE '%Wadi%'");
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));


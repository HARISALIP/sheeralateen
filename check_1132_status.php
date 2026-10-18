<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$stmt = $db->query("SELECT id, order_number, current_status, leajlak_status, leajlak_order_id, leajlak_captain_name, updated_at FROM orders WHERE order_number LIKE '%1132%' OR id = 6");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n--- STATUS HISTORY ---\n";
$stmtH = $db->query("SELECT id, old_status, new_status, notes, created_at FROM order_status_history WHERE order_id = 6 ORDER BY id ASC");
print_r($stmtH->fetchAll(PDO::FETCH_ASSOC));


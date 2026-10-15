<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$stmt = $db->query("SELECT id, order_number, current_status, leajlak_status, leajlak_order_id, leajlak_captain_name, updated_at FROM orders WHERE id = 4");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

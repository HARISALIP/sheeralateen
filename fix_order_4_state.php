<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

// Reset order 4 to clean re-push state
$db->query("UPDATE orders SET leajlak_status = 'New Order', leajlak_captain_name = NULL, leajlak_captain_phone = NULL WHERE id = 4");
echo "Order #4 re-push state cleaned successfully!\n";

$stmt = $db->query("SELECT id, order_number, current_status, leajlak_status, leajlak_order_id, leajlak_captain_name FROM orders WHERE id = 4");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

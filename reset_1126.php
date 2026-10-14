<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$db->query("UPDATE orders SET current_status = 'New', leajlak_status = NULL, leajlak_order_id = NULL WHERE order_number LIKE '%1126%'");
echo "Order #1126 reset to New successfully!\n";

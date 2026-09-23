<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$stmt = $db->query("SELECT payment_status, leajlak_shop_id FROM orders WHERE order_number = '#1100'");
$order = $stmt->fetch(PDO::FETCH_ASSOC);

print_r($order);

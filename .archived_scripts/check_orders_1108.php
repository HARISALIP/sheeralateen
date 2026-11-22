<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT id, order_number, shopify_order_id FROM orders ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

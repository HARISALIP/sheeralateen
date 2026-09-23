<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$sid = "1108";
$stmt = $db->prepare("SELECT id, order_number, current_status, shopify_order_id FROM orders WHERE (shopify_order_id = :sid OR order_number = :sid OR order_number = CONCAT('#', :sid))");
$stmt->execute([':sid' => $sid]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

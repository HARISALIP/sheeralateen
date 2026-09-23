<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$api = new ShopifyService($db);

$shopifyOrderId = "5798993887413"; // Using order #1097 if we know its shopify_order_id, but wait, I don't know its Shopify ID.
// Let's fetch it from the database.
$stmt = $db->query("SELECT shopify_order_id FROM orders WHERE order_number = '#1097'");
$row = $stmt->fetch();
if (!$row) die("Order not found");
$shopifyOrderId = $row['shopify_order_id'];

try {
    echo "Creating transaction for Shopify Order ID: $shopifyOrderId\n";
    $result = $api->createTransaction($shopifyOrderId, 'sale');
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

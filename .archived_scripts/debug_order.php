<?php
/**
 * TEMPORARY DIAGNOSTIC SCRIPT
 * Upload to: /public_html/shopify-theme-delivery/debug_order.php
 */
require_once __DIR__ . '/core/bootstrap.php';

// Force show errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

$db = Database::getConnection();
$api = new ShopifyService($db);

$orderNumber = $_GET['order'] ?? '#1061';
$orderNumber = str_replace('#', '', $orderNumber); // Normalize

// Get orders to find the ID
$response = $api->getOrders(['status' => 'any', 'limit' => 5, 'query' => $orderNumber]);

if (empty($response['orders'])) {
    exit("Order {$orderNumber} not found in Shopify.");
}

$order = null;
foreach ($response['orders'] as $o) {
    if (strpos($o['name'], $orderNumber) !== false) {
        $order = $o;
        break;
    }
}

if (!$order) {
    exit("Order {$orderNumber} found in response but name didn't match.");
}

header('Content-Type: application/json');
echo json_encode([
    'order_name' => $order['name'],
    'note_attributes' => $order['note_attributes'] ?? 'MISSING',
    'tags' => $order['tags'] ?? ''
], JSON_PRETTY_PRINT);

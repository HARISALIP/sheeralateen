<?php
require_once __DIR__ . '/core/bootstrap.php';

$db = Database::getConnection();
$shopify = new ShopifyService($db);

try {
    echo "Fetching recent orders...\n";
    $orders = $shopify->getOrders(['limit' => 1, 'status' => 'any']);
    
    if (empty($orders['orders'])) {
        echo "No orders found.\n";
        exit;
    }
    
    $order = $orders['orders'][0];
    $orderId = $order['id'];
    echo "Found Order ID: {$orderId}\n";
    
    // Call fulfillment orders
    $reflection = new ReflectionClass($shopify);
    $method = $reflection->getMethod('request');
    $method->setAccessible(true);
    
    echo "Fetching fulfillment orders...\n";
    $fulfillmentOrdersResponse = $method->invokeArgs($shopify, ['GET', "/orders/{$orderId}/fulfillment_orders.json"]);
    
    $fulfillmentOrders = $fulfillmentOrdersResponse['fulfillment_orders'] ?? [];
    
    if (empty($fulfillmentOrders)) {
        echo "No fulfillment orders found for this order.\n";
    } else {
        $fo = $fulfillmentOrders[0];
        echo "\n--- FULFILLMENT ORDER JSON ---\n";
        echo json_encode($fo, JSON_PRETTY_PRINT) . "\n";
        
        echo "\n--- VERIFICATION ---\n";
        if (isset($fo['assigned_location'])) {
            echo "assigned_location EXISTS.\n";
            $loc = $fo['assigned_location'];
            echo "Location ID: " . ($loc['location_id'] ?? 'MISSING') . "\n";
            echo "Location Name: " . ($loc['name'] ?? 'MISSING') . "\n";
            echo "Address: " . ($loc['address1'] ?? 'MISSING') . ", " . ($loc['city'] ?? 'MISSING') . "\n";
        } else {
            echo "assigned_location DOES NOT EXIST in the response.\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

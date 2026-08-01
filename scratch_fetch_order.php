<?php
require_once __DIR__ . '/core/bootstrap.php';

$db = Database::getConnection();
$shopify = new ShopifyService($db);

try {
    // Fetch latest 1 order
    $orders = $shopify->getOrders(['limit' => 1, 'status' => 'any']);
    
    if (empty($orders['orders'])) {
        echo "No orders found.\n";
        exit;
    }
    
    $order = $orders['orders'][0];
    
    $orderId = $order['id'];
    
    $reflection = new ReflectionClass($shopify);
    $method = $reflection->getMethod('request');
    $method->setAccessible(true);
    
    $fulfillmentOrders = $method->invokeArgs($shopify, ['GET', "/orders/{$orderId}/fulfillment_orders.json"]);
    
    $output = [
        'order' => $order,
        'fulfillment_orders' => $fulfillmentOrders['fulfillment_orders'] ?? []
    ];
    
    file_put_contents(__DIR__ . '/scratch_order_dump.json', json_encode($output, JSON_PRETTY_PRINT));
    echo "Order saved to scratch_order_dump.json\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

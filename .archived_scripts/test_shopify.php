<?php
require_once __DIR__ . '/../core/bootstrap.php';
$db = Database::getConnection();
$api = new ShopifyService($db);

try {
    echo "<h1>Shopify API Test</h1>";
    echo "<h2>Testing Connection...</h2>";
    $test = $api->testConnection();
    echo "<pre>" . print_r($test, true) . "</pre>";
    
    if (!$test['ok']) {
        die("API connection failed. Please check your token.");
    }
    
    echo "<h2>Fetching Latest Order...</h2>";
    $response = $api->getOrders(['limit' => 1, 'status' => 'any']);
    
    if (empty($response['orders'])) {
        echo "<p>No orders found on Shopify!</p>";
    } else {
        $order = $response['orders'][0];
        echo "<p>Found Order: <strong>{$order['name']}</strong></p>";
        echo "<p>Created At: {$order['created_at']}</p>";
        
        echo "<h2>Fetching Fulfillment Orders for {$order['name']}...</h2>";
        $foResponse = $api->getOrderFulfillmentOrders($order['id']);
        $fulfillmentOrders = $foResponse['fulfillment_orders'] ?? [];
        
        if (empty($fulfillmentOrders)) {
            echo "<p>No fulfillment orders found for this order.</p>";
        } else {
            $locationId = $fulfillmentOrders[0]['assigned_location']['location_id'] ?? 'NONE';
            echo "<p>Shopify Assigned Location ID: <strong>{$locationId}</strong></p>";
            
            echo "<h2>Checking Database for Matching Branch...</h2>";
            $stmt = $db->prepare("SELECT id, branch_name FROM branches WHERE shopify_location_id = :loc");
            $stmt->execute([':loc' => $locationId]);
            $branch = $stmt->fetch();
            
            if ($branch) {
                echo "<p style='color:green; font-weight:bold;'>Match Found! Branch: {$branch['branch_name']} (ID: {$branch['id']})</p>";
            } else {
                echo "<p style='color:red; font-weight:bold;'>No branch in your database has shopify_location_id = {$locationId}!</p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<h2>Exception:</h2>";
    echo "<p style='color:red;'>" . $e->getMessage() . "</p>";
}

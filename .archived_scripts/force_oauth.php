<?php
require_once __DIR__ . '/../core/bootstrap.php';
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop = trim($_POST['shop']);
    $clientId = trim($_POST['client_id']);
    $clientSecret = trim($_POST['client_secret']);
    
    save_setting($db, 'shopify_client_id', $clientId);
    save_setting($db, 'shopify_client_secret', $clientSecret);
    
    $redirectUri = "https://sheeralateen.fix4.in/admin/shopify_oauth.php";
    // Added read_inventory just in case, but definitely has read_locations
    $scopes = "read_orders,write_orders,read_products,read_customers,read_merchant_managed_fulfillment_orders,write_merchant_managed_fulfillment_orders,read_assigned_fulfillment_orders,read_third_party_fulfillment_orders,read_fulfillments,write_fulfillments,read_locations,read_inventory";
    
    $authUrl = "https://$shop/admin/oauth/authorize?client_id=$clientId&scope=$scopes&redirect_uri=" . urlencode($redirectUri) . "&state=99999&grant_options[]=";
    
    // Output a direct clickable link to bypass any PHP header caching issues
    echo "<h1>Step 1 Complete</h1>";
    echo "<p>Client Secret saved temporarily.</p>";
    echo "<p><strong><a href='$authUrl' style='font-size:20px; color:blue; text-decoration:underline;'>CLICK HERE TO GO TO SHOPIFY AND APPROVE PERMISSIONS</a></strong></p>";
    exit;
}
?>
<form method="POST">
    <h3>Force OAuth</h3>
    Shop: <input type="text" name="shop" value="sheeralateen-01.myshopify.com"><br><br>
    Client ID: <input type="text" name="client_id" value="eede6b06ae05a701552b89dff604cf2b" style="width:300px;"><br><br>
    Client Secret: <input type="password" name="client_secret" required><br><br>
    <button type="submit">Prepare OAuth</button>
</form>

<?php
/**
 * admin/shopify_oauth.php
 * A one-time utility to perform the Shopify OAuth flow for Dev Dashboard Apps.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

// 1. Check if returning from Shopify with a code
if (isset($_GET['code']) && isset($_GET['shop'])) {
    $code = $_GET['code'];
    $shop = $_GET['shop'];
    
    $clientId = get_setting($db, 'shopify_client_id');
    $clientSecret = get_setting($db, 'shopify_client_secret');
    
    if (!$clientId || !$clientSecret) {
        die("Client ID or Secret missing. Please submit them first.");
    }
    
    // Exchange code for access token
    $ch = curl_init("https://$shop/admin/oauth/access_token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['access_token'])) {
        $token = $data['access_token'];
        
        // Save the permanent offline access token
        $encryptedToken = encrypt_shopify_token($token);
        save_setting($db, 'shopify_api_token', $encryptedToken);
        
        // Clear temp secrets
        save_setting($db, 'shopify_client_id', '');
        save_setting($db, 'shopify_client_secret', '');
        
        flash('success', 'Successfully generated and saved Shopify Admin API Token!');
        header('Location: developer.php');
        exit;
    } else {
        die("OAuth Failed. Shopify responded: " . htmlspecialchars($response));
    }
}

// 2. Form to start the flow
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop = trim($_POST['shop']);
    $clientId = trim($_POST['client_id']);
    $clientSecret = trim($_POST['client_secret']);
    
    // Save temporarily
    save_setting($db, 'shopify_client_id', $clientId);
    save_setting($db, 'shopify_client_secret', $clientSecret);
    
    $redirectUri = "https://sheeralateen.fix4.in/admin/shopify_oauth.php";
    $scopes = "read_orders,write_orders,read_products,read_customers,read_merchant_managed_fulfillment_orders,write_merchant_managed_fulfillment_orders,read_assigned_fulfillment_orders,read_third_party_fulfillment_orders,read_fulfillments,write_fulfillments,read_locations";
    
    $authUrl = "https://$shop/admin/oauth/authorize?client_id=$clientId&scope=$scopes&redirect_uri=" . urlencode($redirectUri) . "&state=12345&grant_options[]=";
    
    header("Location: $authUrl");
    exit;
}

$pageTitle = 'Shopify OAuth Capture';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Shopify OAuth Generator</h1>
        <p class="page-subtitle">Since Shopify forced the Dev Dashboard, use this tool to generate your permanent API token.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Shop URL</label>
                <input type="text" name="shop" class="form-control" placeholder="sheeralateen-01.myshopify.com" required>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Client ID (from Dev Dashboard)</label>
                <input type="text" name="client_id" class="form-control" required>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Client Secret (from Dev Dashboard)</label>
                <input type="password" name="client_secret" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate Token</button>
        </form>
        <div class="alert alert-info" style="margin-top:20px;">
            <p><strong>Instructions:</strong></p>
            <ol>
                <li>In your Dev Dashboard app, go to <strong>Configuration</strong>.</li>
                <li>Find the <strong>App URL</strong> and <strong>Allowed redirection URL(s)</strong> sections.</li>
                <li>Set the Allowed redirection URL to exactly: <br><code>https://sheeralateen.fix4.in/admin/shopify_oauth.php</code></li>
                <li>Save in Shopify.</li>
                <li>Come back here, paste your Client ID and Secret, and click Generate Token.</li>
            </ol>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

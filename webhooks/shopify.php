<?php
/**
 * webhooks/shopify.php
 * Shopify Webhook Listener
 * 
 * Handles real-time order updates from Shopify.
 */
require_once __DIR__ . '/../core/bootstrap.php';

// 1. Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$db = Database::getConnection();

// 2. Extract Shopify Headers
$hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
$topicHeader = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';
$webhookId = $_SERVER['HTTP_X_SHOPIFY_WEBHOOK_ID'] ?? '';
$shopHeader = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? '';

if (!$hmacHeader || !$topicHeader || !$webhookId) {
    http_response_code(401);
    exit('Missing Shopify Headers');
}

// 3. Prevent Duplicate Processing
// Check if webhook_id already exists in webhook_logs
try {
    $stmt = $db->prepare("SELECT id FROM webhook_logs WHERE webhook_id = :wid LIMIT 1");
    $stmt->execute([':wid' => $webhookId]);
    if ($stmt->fetch()) {
        http_response_code(200); // Already processed, return 200 to satisfy Shopify
        exit('Duplicate webhook');
    }
} catch (PDOException $e) {
    // If webhook_id column doesn't exist yet, it will throw an error. 
    // We catch it and silently ignore duplicate checking until the DB is updated.
}

// 4. Read Raw Payload
$rawPayload = file_get_contents('php://input');

// 5. Verify HMAC Signature
$secret = get_setting($db, 'shopify_webhook_secret', '');
if (empty($secret)) {
    http_response_code(500);
    exit('Webhook secret not configured');
}

$calculatedHmac = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));
if (!hash_equals($calculatedHmac, $hmacHeader)) {
    http_response_code(401);
    exit('HMAC Verification Failed');
}

// 6. Return 200 OK Immediately
// FastCGI/PHP-FPM trick to send response and continue processing
if (function_exists('fastcgi_finish_request')) {
    http_response_code(200);
    fastcgi_finish_request();
} else {
    // Fallback for non-FPM
    ob_start();
    http_response_code(200);
    echo "OK";
    header("Connection: close");
    header("Content-Length: " . ob_get_length());
    ob_end_flush();
    @ob_flush();
    flush();
}

// ====================================================================
// ASYNCHRONOUS PROCESSING (After 200 OK)
// ====================================================================

$payload = json_decode($rawPayload, true);
$errorMessage = null;
$processed = 0;

try {
    if ($payload) {
        $api = new ShopifyService($db);
        $queue = new SyncQueue($db);
        $sync = new ShopifySyncService($db, $api, $queue);

        if (in_array($topicHeader, ['orders/create', 'orders/updated', 'orders/cancelled'])) {
            $sync->importSingleOrder($payload);
            $processed = 1;
        } else {
            $errorMessage = "Unhandled topic: $topicHeader";
        }
    } else {
        $errorMessage = "Invalid JSON payload";
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine();
}

// 7. Log the Webhook
try {
    $stmt = $db->prepare("
        INSERT INTO webhook_logs (webhook_id, topic, payload, processed, error_message, received_at) 
        VALUES (:wid, :topic, :payload, :processed, :error, NOW())
    ");
    $stmt->execute([
        ':wid' => $webhookId,
        ':topic' => $topicHeader,
        ':payload' => $rawPayload,
        ':processed' => $processed,
        ':error' => $errorMessage
    ]);
} catch (PDOException $e) {
    // Fallback if webhook_id column wasn't created by user yet
    $stmt = $db->prepare("
        INSERT INTO webhook_logs (topic, payload, processed, error_message, received_at) 
        VALUES (:topic, :payload, :processed, :error, NOW())
    ");
    $stmt->execute([
        ':topic' => $topicHeader,
        ':payload' => $rawPayload,
        ':processed' => $processed,
        ':error' => $errorMessage
    ]);
}

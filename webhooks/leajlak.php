<?php
/**
 * webhooks/leajlak.php
 * ---------------------------------------------------------
 * Leajlak (staging.4ulogistic.com) Webhook Receiver
 *
 * Receives real-time delivery status updates from Leajlak and
 * maps them to this app's local order lifecycle statuses.
 *
 * ── Authentication ──────────────────────────────────────────
 * Leajlak sends the configured "Webhook Secret Key" verbatim
 * as the value of the Authorization HTTP header (no Bearer
 * prefix). Example:
 *   Authorization: your-secret-token
 *
 * Store your secret in system_settings:
 *   key   = leajlak_webhook_secret
 *   value = <whatever you entered in Leajlak Config Access>
 *
 * NOTE (Apache + PHP-FPM): If the Authorization header is
 * missing, add this line to your root .htaccess:
 *   SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
 *
 * ── Confirmed Payload (live webhook.site capture 2026-08-31) ─
 * POST application/json  User-Agent: GuzzleHttp/7
 * {
 *   "id":           "<shopify_order_id>",  // → orders.shopify_order_id
 *   "status":       "Delivered",           // Leajlak status string
 *   "dsp_order_id": 6480                   // Leajlak internal delivery ID
 * }
 *
 * ── Status Mapping ──────────────────────────────────────────
 * Leajlak status            → local current_status
 * --------------------------  --------------------------
 * Order Accept              → Accepted
 * Start Ride                → Accepted
 * Reached shop              → Preparing
 * Order Picked              → Preparing
 * Shipped                   → Out For Delivery
 * Reached Destination       → Out For Delivery
 * Re Route                  → Out For Delivery
 * Delivered                 → Delivered         (+ Shopify fulfillment via queue)
 * Cancel Request Accepted   → Cancelled         (+ Shopify cancellation via queue)
 * Canceled                  → Cancelled         (+ Shopify cancellation via queue)
 * Return To Foryou          → Returned
 * Return To Client          → Returned
 * Client Return Accepted    → Returned
 * [all others]              → no-op (logged, not an error)
 */

require_once __DIR__ . '/../core/bootstrap.php';

// ── 1. Enforce POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$db = Database::getConnection();

// ── 2. Read raw body (must happen before any output) ────────
$rawPayload = file_get_contents('php://input');

// ── 3. Authenticate ─────────────────────────────────────────
// Leajlak sends the Webhook Secret Key verbatim in the
// Authorization header — no "Bearer" prefix, just the raw value.
// Fall back to getallheaders() for environments where Apache
// strips Authorization before it reaches $_SERVER.
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (strtolower($name) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
}

$expectedSecret = get_setting($db, 'leajlak_webhook_secret', '');

if (empty($expectedSecret)) {
    error_log('Leajlak webhook: leajlak_webhook_secret is not configured in system_settings.');
    http_response_code(500);
    exit('Webhook secret not configured');
}

if (!hash_equals($expectedSecret, $authHeader)) {
    $headerPresent = !empty($authHeader) ? 'yes' : 'no';
    $authFailMsg   = 'Authorization failed (header present: ' . $headerPresent . ')';
    error_log('Leajlak webhook: ' . $authFailMsg . '.');
    try {
        $db->prepare("
            INSERT INTO webhook_logs
                (topic, payload, processed, error_message, received_at)
            VALUES
                (:topic, :payload, :processed, :error, NOW())
        ")->execute([
            ':topic'     => 'leajlak/order_status',
            ':payload'   => $rawPayload,
            ':processed' => 0,
            ':error'     => $authFailMsg,
        ]);
    } catch (PDOException $logEx) {
        error_log('Leajlak webhook: Failed to log auth failure to webhook_logs: ' . $logEx->getMessage());
    }
    http_response_code(401);
    exit('Unauthorized');
}

// ── 4. Return 200 OK immediately ─────────────────────────────
// Leajlak uses GuzzleHttp and expects a fast acknowledgement.
// Send 200 now and continue processing asynchronously below.
if (function_exists('fastcgi_finish_request')) {
    http_response_code(200);
    fastcgi_finish_request();
} else {
    ob_start();
    http_response_code(200);
    echo 'OK';
    header('Connection: close');
    header('Content-Length: ' . ob_get_length());
    ob_end_flush();
    @ob_flush();
    flush();
}

// ====================================================================
// ASYNC PROCESSING (after 200 OK is already sent to Leajlak)
// ====================================================================

/**
 * Leajlak status string → local current_status ENUM value.
 *
 * Statuses absent from this map are informational / transitional
 * and produce no local state change. They are logged as noops,
 * not as errors. Unmapped statuses:
 *   Ticket Raised, Request For Cancel, Pending
 */
$statusMap = [
    'Order Accept'            => 'Accepted',
    'Start Ride'              => 'Accepted',
    'Reached shop'            => 'Preparing',
    'Order Picked'            => 'Preparing',
    'Shipped'                 => 'Out For Delivery',
    'Reached Destination'     => 'Out For Delivery',
    'Re Route'                => 'Out For Delivery',
    'Delivered'               => 'Delivered',
    'Cancel Request Accepted' => 'Cancelled',
    'Canceled'                => 'Cancelled',
    'Return To Foryou'        => 'Returned',
    'Return To Client'        => 'Returned',
    'Client Return Accepted'  => 'Returned',
];

$errorMessage = null;
$processed    = 0;

// do-while(false) lets us `break` on early exits while still
// reaching the webhook_logs INSERT at the bottom.
do {

    // ── 5. Parse payload ────────────────────────────────────
    $payload = json_decode($rawPayload, true);

    if (!is_array($payload) || !isset($payload['id'], $payload['status'])) {
        $errorMessage = 'Invalid JSON payload: missing id or status field.';
        error_log('Leajlak webhook: ' . $errorMessage);
        // Raw payload is stored in webhook_logs below — not repeated here.
        break;
    }

    $shopifyOrderId = (string) $payload['id'];
    $leajlakStatus  = (string) $payload['status'];
    $dspOrderId     = isset($payload['dsp_order_id']) ? (int) $payload['dsp_order_id'] : null;

    // ── 6. Resolve local status ──────────────────────────────
    if (!array_key_exists($leajlakStatus, $statusMap)) {
        // Informational status with no local transition — not an error.
        $processed = 1;
        ActivityLogger::log(
            null,
            'leajlak_webhook_noop',
            "Leajlak status '{$leajlakStatus}' for Shopify order {$shopifyOrderId} "
            . 'has no local mapping — skipped.'
        );
        break;
    }

    $newLocalStatus = $statusMap[$leajlakStatus];

    // ── 7. Find the local order by Shopify order ID ─────────
    $stmt = $db->prepare("
        SELECT id, order_number, current_status, shopify_order_id
        FROM   orders
        WHERE  shopify_order_id = :sid
          AND  deleted_at IS NULL
        LIMIT  1
    ");
    $stmt->execute([':sid' => $shopifyOrderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $errorMessage = "Order not found for Shopify ID: {$shopifyOrderId}";
        error_log('Leajlak webhook: ' . $errorMessage);
        break;
    }

    $localOrderId  = (int)    $order['id'];
    $orderNumber   = (string) $order['order_number'];
    $currentStatus = (string) $order['current_status'];

    // ── Idempotency guard — skip if already at target status ─
    if ($currentStatus === $newLocalStatus) {
        $processed = 1;
        ActivityLogger::log(
            null,
            'leajlak_webhook_noop',
            "Order {$orderNumber} already at '{$newLocalStatus}' "
            . '— duplicate Leajlak event skipped.'
        );
        break;
    }

    // ── 8. Update local order status ─────────────────────────
    $db->prepare("
        UPDATE orders
        SET    current_status = :status,
               updated_at     = NOW()
        WHERE  id = :id
    ")->execute([
        ':status' => $newLocalStatus,
        ':id'     => $localOrderId,
    ]);

    // ── 9. Write status history (changed_by = NULL = system) ─
    $db->prepare("
        INSERT INTO order_status_history
            (order_id, old_status, new_status, changed_by, notes)
        VALUES
            (:oid, :old, :new, NULL, :notes)
    ")->execute([
        ':oid'   => $localOrderId,
        ':old'   => $currentStatus,
        ':new'   => $newLocalStatus,
        ':notes' => 'Leajlak webhook: ' . $leajlakStatus
                    . ($dspOrderId ? " (DSP order #{$dspOrderId})" : ''),
    ]);

    // ── 10. Enqueue Shopify push ──────────────────────────────
    // ShopifySyncService::processOrderJob() handles automatically:
    //   Delivered  → ShopifyService::createFulfillment()
    //   Cancelled  → ShopifyService::cancelOrder()
    //   All others → ShopifyService::updateOrderTags()
    $queue = new SyncQueue($db);
    $queue->enqueue('order', $localOrderId, $shopifyOrderId, 'push_status');

    // Attempt immediate processing; cron will retry any failures.
    try {
        $api  = new ShopifyService($db);
        $sync = new ShopifySyncService($db, $api, $queue);
        $sync->processQueue(5);
    } catch (Exception $syncEx) {
        error_log(
            'Leajlak webhook: Inline Shopify sync failed (cron will retry): '
            . $syncEx->getMessage()
        );
    }

    // ── 11. Audit log ─────────────────────────────────────────
    ActivityLogger::log(
        null,
        'leajlak_status_update',
        "Order {$orderNumber}: '{$currentStatus}' → '{$newLocalStatus}'"
        . " (Leajlak: '{$leajlakStatus}')"
        . ($dspOrderId ? " DSP#{$dspOrderId}" : ''),
        null,
        $localOrderId
    );

    $processed = 1;

} while (false);

// ── 12. Log every request to webhook_logs ────────────────────
try {
    $db->prepare("
        INSERT INTO webhook_logs
            (topic, payload, processed, error_message, received_at)
        VALUES
            (:topic, :payload, :processed, :error, NOW())
    ")->execute([
        ':topic'     => 'leajlak/order_status',
        ':payload'   => $rawPayload,
        ':processed' => $processed,
        ':error'     => $errorMessage,
    ]);
} catch (PDOException $logEx) {
    error_log(
        'Leajlak webhook: Failed to write to webhook_logs: '
        . $logEx->getMessage()
    );
}

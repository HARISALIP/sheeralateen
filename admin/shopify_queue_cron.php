<?php
/**
 * admin/shopify_queue_cron.php
 * ---------------------------------------------------------
 * Cron script to process the synchronization queue (Push to Shopify).
 * 
 * Usage (CLI or Web with secret):
 * php shopify_queue_cron.php
 * https://sheeralateen.fix4.in/admin/shopify_queue_cron.php?secret=YOUR_CRON_SECRET
 */
require_once __DIR__ . '/../core/bootstrap.php';

// Allow execution from CLI or with the correct secret via Web
$isCli = (php_sapi_name() === 'cli');
$secret = $_GET['secret'] ?? '';

if (!$isCli && $secret !== CRON_SECRET) {
    http_response_code(403);
    die("Forbidden: Invalid cron secret.\n");
}

try {
    $db = Database::getConnection();
    $api = new ShopifyService($db);
    $queue = new SyncQueue($db);
    $syncService = new ShopifySyncService($db, $api, $queue);

    if (!$api->isConfigured()) {
        echo "Error: Shopify API is not configured.\n";
        exit;
    }

    echo "Processing Shopify Sync Queue...\n";
    $startTime = microtime(true);

    // Process up to 20 jobs at a time to stay within limits and prevent long execution
    $stats = $syncService->processQueue(20);

    $duration = round(microtime(true) - $startTime, 2);

    echo "Queue processing completed in {$duration}s.\n";
    echo "Processed Successfully: {$stats['processed']}\n";
    echo "Failed Jobs: {$stats['failed']}\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    ActivityLogger::log(null, 'cron_queue_failed', $e->getMessage());
}

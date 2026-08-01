<?php
/**
 * admin/shopify_import_cron.php
 * ---------------------------------------------------------
 * Cron script to import new/updated orders from Shopify.
 * 
 * Usage (CLI or Web with secret):
 * php shopify_import_cron.php
 * https://sheeralateen.fix4.in/admin/shopify_import_cron.php?secret=YOUR_CRON_SECRET
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
    
    // Check if enough time has passed based on sync_interval_minutes setting
    $intervalMin = (int) get_setting($db, 'sync_interval_minutes', '5');
    $lastSyncStr = get_setting($db, 'shopify_last_sync_time', '');
    
    if ($lastSyncStr) {
        $lastSync = new DateTime($lastSyncStr);
        $now = new DateTime();
        $diffMin = ($now->getTimestamp() - $lastSync->getTimestamp()) / 60;
        
        // Add a 1-minute grace period to prevent skipped cron jobs
        if ($diffMin < ($intervalMin - 1)) {
            echo "Skipping import: Only " . round($diffMin) . " minutes since last sync (Interval: {$intervalMin}m).\n";
            exit;
        }
    }

    echo "Starting Shopify Order Import...\n";
    $startTime = microtime(true);

    $api = new ShopifyService($db);
    $queue = new SyncQueue($db);
    $syncService = new ShopifySyncService($db, $api, $queue);

    if (!$api->isConfigured()) {
        echo "Error: Shopify API is not configured.\n";
        exit;
    }

    $stats = $syncService->importOrders();

    $duration = round(microtime(true) - $startTime, 2);

    echo "Import completed in {$duration}s.\n";
    echo "Imported: {$stats['imported']}\n";
    echo "Updated: {$stats['updated']}\n";
    
    if (!empty($stats['errors'])) {
        echo "Errors encountered:\n";
        foreach ($stats['errors'] as $error) {
            echo "- $error\n";
        }
    }

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    ActivityLogger::log(null, 'cron_import_failed', $e->getMessage());
}

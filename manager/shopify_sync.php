<?php
/**
 * admin/shopify_sync.php
 * ---------------------------------------------------------
 * AJAX endpoint to manually trigger a Shopify sync (import + process queue).
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('branch_manager', '../login.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'message' => 'Security token mismatch.']);
    exit;
}

try {
    $db = Database::getConnection();
    $api = new ShopifyService($db);
    $queue = new SyncQueue($db);
    $syncService = new ShopifySyncService($db, $api, $queue);

    // 1. Process Queue (push local changes to Shopify)
    $queueStats = $syncService->processQueue(20);

    // 2. Import Orders (pull changes from Shopify)
    $importStats = $syncService->importOrders();

    echo json_encode([
        'ok' => true,
        'message' => 'Sync completed successfully.',
        'stats' => [
            'imported' => $importStats['imported'],
            'updated' => $importStats['updated'],
            'errors' => count($importStats['errors']),
            'queue_processed' => $queueStats['processed'],
            'queue_failed' => $queueStats['failed']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

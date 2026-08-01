<?php
/**
 * admin/shopify_test_connection.php
 * ---------------------------------------------------------
 * AJAX endpoint to test Shopify API credentials.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

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
    
    // Check if we are testing newly submitted unsaved credentials or existing ones
    // Actually, in settings, we should save first, then test.
    
    $api = new ShopifyService($db);
    $result = $api->testConnection();
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

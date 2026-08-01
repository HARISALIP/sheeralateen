<?php
/**
 * api/branches.php
 * ---------------------------------------------------------
 * Public lightweight API to return active branches.
 * Used by Shopify storefront for dynamic dropdown population.
 */

// Temporarily enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Allow Shopify storefront to call it

// Check if required files exist before requiring them
$configPath = __DIR__ . '/../config/config.php';
$dbPath = __DIR__ . '/../core/Database.php';

if (!file_exists($configPath)) {
    die(json_encode(['status' => 'error', 'message' => 'config.php not found at ' . $configPath]));
}
if (!file_exists($dbPath)) {
    die(json_encode(['status' => 'error', 'message' => 'Database.php not found at ' . $dbPath]));
}

require_once $configPath;
require_once $dbPath;

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT id, branch_name as name FROM branches WHERE status = 'active' ORDER BY branch_name ASC");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $branches
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

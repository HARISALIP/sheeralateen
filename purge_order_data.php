<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== PURGING ORDER DATA FOR PRODUCTION CLEAN SLATE ===\n";

try {
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $db->exec("TRUNCATE TABLE order_items");
    echo "? Truncated order_items\n";

    $db->exec("TRUNCATE TABLE order_status_history");
    echo "? Truncated order_status_history\n";

    $db->exec("TRUNCATE TABLE order_notes");
    echo "? Truncated order_notes\n";

    $db->exec("TRUNCATE TABLE orders");
    echo "? Truncated orders\n";

    $db->exec("TRUNCATE TABLE sync_queue");
    echo "? Truncated sync_queue\n";

    $db->exec("TRUNCATE TABLE webhook_logs");
    echo "? Truncated webhook_logs\n";

    $stmtAct = $db->exec("DELETE FROM activity_logs WHERE order_id IS NOT NULL OR action LIKE 'order%' OR action LIKE 'leajlak%' OR action LIKE 'shopify%'");
    echo "? Cleaned activity_logs related to orders/leajlak ($stmtAct rows removed)\n";

    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Set shopify_last_sync_time to now so old Shopify test orders are not re-pulled
    save_setting($db, 'shopify_last_sync_time', date('Y-m-d H:i:s'));
    echo "? Updated shopify_last_sync_time to current timestamp\n";

    echo "\n=== REMAINING RECORD COUNTS ===\n";
    echo "orders: " . $db->query("SELECT COUNT(*) FROM orders")->fetchColumn() . " rows\n";
    echo "order_items: " . $db->query("SELECT COUNT(*) FROM order_items")->fetchColumn() . " rows\n";
    echo "order_status_history: " . $db->query("SELECT COUNT(*) FROM order_status_history")->fetchColumn() . " rows\n";
    echo "branches: " . $db->query("SELECT COUNT(*) FROM branches")->fetchColumn() . " rows (PRESERVED)\n";
    echo "users (managers & admins): " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . " rows (PRESERVED)\n";
    echo "system_settings: " . $db->query("SELECT COUNT(*) FROM system_settings")->fetchColumn() . " rows (PRESERVED)\n";
    echo "\nAll order data successfully cleaned!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

<?php
/**
 * clean_orders.php
 * ---------------------------------------------------------------------------
 * Cleans all test orders (#1127 through #1141) from the database to start fresh.
 */
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

try {
    $db->beginTransaction();

    // 1. Delete associated records
    $db->exec("DELETE FROM sync_queue WHERE entity_type = 'order'");
    $db->exec("DELETE FROM order_items");
    $db->exec("DELETE FROM order_notes");
    $db->exec("DELETE FROM order_status_history");

    // 2. Delete all orders
    $count = $db->exec("DELETE FROM orders");

    // 3. Reset auto-increment
    $db->exec("ALTER TABLE orders AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE order_items AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE order_status_history AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE order_notes AUTO_INCREMENT = 1");

    // 4. Log the activity
    $stmt = $db->prepare("INSERT INTO activity_logs (action, description, created_at) VALUES ('clean_test_orders', :desc, NOW())");
    $stmt->execute([':desc' => "Purged $count test orders to start fresh"]);

    $db->commit();
    echo "SUCCESS: Purged $count test orders. The orders database is now clean and ready for real orders.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}

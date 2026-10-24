<?php
/**
 * fix_order_1142.php
 * Re-runs importSingleOrder on the stored payload of #1142 to test the fix and update the record.
 */
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

$wl = $db->query("SELECT * FROM webhook_logs WHERE topic = 'orders/create' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$wl) {
    exit("No webhook log found\n");
}

$payload = json_decode($wl['payload'], true);
echo "Testing importSingleOrder on order: " . ($payload['name'] ?? 'N/A') . "\n";

// First, delete the old #1142 record so importSingleOrder does a clean insert with new logic
$db->exec("DELETE FROM orders WHERE order_number = '#1142' OR order_number = '1142'");

$api = new ShopifyService($db);
$queue = new SyncQueue($db);
$sync = new ShopifySyncService($db, $api, $queue);

$result = $sync->importSingleOrder($payload);
echo "importSingleOrder Result: $result\n\n";

$order = $db->query("
    SELECT o.id, o.order_number, o.customer_name, o.customer_phone, o.customer_email, o.assigned_branch_id, b.branch_name, b.branch_code
    FROM orders o
    LEFT JOIN branches b ON o.assigned_branch_id = b.id
    WHERE o.order_number = '#1142' OR o.order_number = '1142'
")->fetch(PDO::FETCH_ASSOC);

echo "=== UPDATED ORDER IN DATABASE ===\n";
print_r($order);

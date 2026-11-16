<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// 1. Check sync_queue for push_payment or order 17
$queue = $db->query("SELECT * FROM sync_queue WHERE order_id = 17 OR action LIKE '%payment%' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
echo "=== SYNC QUEUE PAYMENT / ORDER 17 ===\n";
print_r($queue);

// 2. Check all sync_queue with action push_payment
$pushPaymentQueue = $db->query("SELECT * FROM sync_queue WHERE action = 'push_payment' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== ALL PUSH PAYMENT JOBS ===\n";
print_r($pushPaymentQueue);

// 3. Check order_status_history for order 17
$hist17 = $db->query("SELECT h.*, u.name as user_name FROM order_status_history h LEFT JOIN users u ON h.changed_by = u.id WHERE h.order_id = 17 ORDER BY h.id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== STATUS HISTORY FOR ORDER 17 (#1147) ===\n";
print_r($hist17);

// 4. Check if ANY Leajlak order in history was ever created with payment_type 0
// Let's check all activity logs where action = 'leajlak_order_created'
$leajlakLogs = $db->query("SELECT a.id, a.order_id, a.created_at, o.order_number, o.payment_status FROM activity_logs a LEFT JOIN orders o ON a.order_id = o.id WHERE a.action = 'leajlak_order_created' ORDER BY a.id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== ALL LEAJLAK CREATED ORDERS AND CURRENT PAYMENT STATUS ===\n";
foreach ($leajlakLogs as $ll) {
    echo "Log #{$ll['id']} | Order {$ll['order_number']} (ID: {$ll['order_id']}) | Payment Status: {$ll['payment_status']} | Date: {$ll['created_at']}\n";
}

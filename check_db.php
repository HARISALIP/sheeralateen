<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// 1. Order #1147 details
$o1147 = $db->query("SELECT * FROM orders WHERE order_number = '#1147' OR id = 17")->fetch(PDO::FETCH_ASSOC);
echo "=== ORDER #1147 ===\n";
print_r($o1147);

// 2. Activity logs for Order #1147
$logs1147 = $db->query("SELECT * FROM activity_logs WHERE order_id = 17 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== ACTIVITY LOGS FOR #1147 ===\n";
print_r($logs1147);

// 3. Webhook log for Order #1147
if (!empty($o1147['shopify_order_id'])) {
    $wl = $db->query("SELECT id, topic, received_at, payload FROM webhook_logs WHERE payload LIKE '%" . $o1147['shopify_order_id'] . "%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($wl) {
        $p = json_decode($wl['payload'], true);
        echo "\n=== SHOPIFY WEBHOOK PAYLOAD FOR #1147 ===\n";
        echo "financial_status: " . ($p['financial_status'] ?? 'N/A') . "\n";
        echo "gateway: " . ($p['gateway'] ?? 'N/A') . "\n";
        echo "payment_gateway_names: " . json_encode($p['payment_gateway_names'] ?? []) . "\n";
    }
}

// 4. All leajlak_order_created activity logs
$createdLogs = $db->query("SELECT a.*, o.order_number, o.payment_status, o.total_amount FROM activity_logs a LEFT JOIN orders o ON a.order_id = o.id WHERE a.action = 'leajlak_order_created' ORDER BY a.id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== ALL LEAJLAK_ORDER_CREATED LOGS (" . count($createdLogs) . ") ===\n";
print_r($createdLogs);



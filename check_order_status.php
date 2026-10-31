<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$stmt = $db->query("SELECT payload FROM webhook_logs WHERE payload LIKE '%1148%' ORDER BY id DESC LIMIT 1");
$payloadStr = $stmt->fetchColumn();

if ($payloadStr) {
    $p = json_decode($payloadStr, true);
    echo "Order: {$p['name']}\n";
    echo "Total: {$p['total_price']}\n";
    echo "Subtotal: {$p['subtotal_price']}\n";
    echo "Shipping lines:\n";
    print_r($p['shipping_lines'] ?? 'None');
    echo "Total shipping price set:\n";
    print_r($p['total_shipping_price_set'] ?? 'None');
} else {
    echo "No webhook log for 1148\n";
}






<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// All November orders
$stmt = $db->query("SELECT id, order_number, customer_name, total_amount, payment_status, current_status, leajlak_order_id, leajlak_status, created_at FROM orders WHERE created_at >= '2026-11-01 00:00:00' ORDER BY id ASC");
$novOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== ALL NOVEMBER ORDERS (" . count($novOrders) . ") ===\n";
foreach ($novOrders as $o) {
    echo "ID: {$o['id']} | #{$o['order_number']} | Status: {$o['current_status']} | Payment: {$o['payment_status']} | Total: {$o['total_amount']} | Leajlak ID: " . ($o['leajlak_order_id'] ?: 'None') . " | Leajlak Status: " . ($o['leajlak_status'] ?: 'None') . " | Date: {$o['created_at']}\n";
}

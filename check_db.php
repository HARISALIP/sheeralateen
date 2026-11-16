<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// 1. Check all orders with payment_status = 'paid'
$paidOrders = $db->query("SELECT id, order_number, payment_status, current_status, total_amount, leajlak_order_id, leajlak_status, created_at, updated_at FROM orders WHERE payment_status = 'paid' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "=== ORDERS WITH payment_status = 'paid' (" . count($paidOrders) . ") ===\n";
print_r($paidOrders);

// 2. Summary of payment_status counts
$counts = $db->query("SELECT payment_status, COUNT(*) as cnt FROM orders GROUP BY payment_status")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== PAYMENT STATUS COUNTS ===\n";
print_r($counts);

// 3. Check recent activity logs related to leajlak
$logs = $db->query("SELECT * FROM activity_logs WHERE action LIKE '%leajlak%' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== RECENT LEAJLAK ACTIVITY LOGS ===\n";
print_r($logs);

// 4. Check the latest 10 orders overall
$latestOrders = $db->query("SELECT id, order_number, payment_status, current_status, total_amount, leajlak_order_id, leajlak_status, created_at FROM orders ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== LATEST 10 ORDERS ===\n";
print_r($latestOrders);


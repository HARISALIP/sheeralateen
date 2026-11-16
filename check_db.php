<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// Check order 42 (#1172)
$o42 = $db->query("SELECT * FROM orders WHERE id = 42")->fetch(PDO::FETCH_ASSOC);
echo "=== ORDER 42 (#1172) ===\n";
print_r($o42);

// Activity logs for order 42
$logs42 = $db->query("SELECT a.*, u.name as user_name FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id WHERE a.order_id = 42 ORDER BY a.id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== LOGS FOR ORDER 42 ===\n";
print_r($logs42);

// Check order 35 (#1165) which had jobs 56, 59 push_payment
$o35 = $db->query("SELECT * FROM orders WHERE id = 35")->fetch(PDO::FETCH_ASSOC);
echo "\n=== ORDER 35 (#1165) ===\n";
print_r($o35);

// Check if any order payload sent to Leajlak had payment_type: 0
// Let's inspect the code in core/LeajlakService.php again
echo "\n=== LEAJLAK PAYMENT LOGIC IN CODE ===\n";
echo "Line 30: paymentMode = (order['payment_status'] ?? 'pending') === 'paid' ? 0 (PREPAID) : 1 (CASH)\n";
echo "Line 121: 'payment_type' => (int)paymentMode\n";

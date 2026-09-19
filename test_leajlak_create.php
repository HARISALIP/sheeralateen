<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/LeajlakService.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "=== Leajlak Create Order Test ===\n\n";

$email    = get_setting($db, 'leajlak_email', '');
$password = get_setting($db, 'leajlak_password', '');
echo "leajlak_email    : " . ($email    ? $email : '(NOT SET)') . "\n";
echo "leajlak_password : " . ($password ? '***SET***' : '(NOT SET)') . "\n\n";

if (!$email || !$password) {
    echo "ERROR: Set leajlak_email and leajlak_password in the system_settings table first.\n";
    exit(1);
}

$leajlak = new LeajlakService($db);
$testOrderId = 'TEST-' . date('His');

echo "Creating test order: {$testOrderId}\n";
$result = $leajlak->createOrder(
    $testOrderId,
    '0501234567',
    'Dubai Marina, Dubai, UAE',
    50.00,
    LeajlakService::PAYMENT_CASH
);

echo "Result:\n";
print_r($result);

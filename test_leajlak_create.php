<?php
/**
 * test_leajlak_create.php — ONE-TIME test script, delete after use.
 * Tests the LeajlakService order creation end-to-end.
 * Run from browser: https://sheeralateen.fix4.in/test_leajlak_create.php
 */
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/LeajlakService.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "=== Leajlak Create Order Test ===\n\n";

// Check credentials are set
$email    = get_setting($db, 'leajlak_email', '');
$password = get_setting($db, 'leajlak_password', '');
echo "leajlak_email    : " . ($email    ? $email : '(NOT SET)') . "\n";
echo "leajlak_password : " . ($password ? '***SET***' : '(NOT SET)') . "\n\n";

if (!$email || !$password) {
    echo "ERROR: Set leajlak_email and leajlak_password in the system_settings table first.\n";
    exit(1);
}

$leajlak = new LeajlakService($db);

// Use a unique test order ID so we don't conflict with existing ones.
$testOrderId = 'TEST-' . date('His');

echo "Creating test order: {$testOrderId}\n";
echo "Phone  : 0501234567\n";
echo "Address: Dubai Marina, Dubai, UAE\n";
echo "Amount : 50.00 SAR\n";
echo "Payment: Cash\n\n";

$result = $leajlak->createOrder(
    $testOrderId,
    '0501234567',
    'Dubai Marina, Dubai, UAE',
    50.00,
    LeajlakService::PAYMENT_CASH
);

echo "Result:\n";
echo "  success          : " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "  leajlak_order_id : " . ($result['leajlak_order_id'] ?? 'null') . "\n";
echo "  error            : " . ($result['error'] ?? 'none') . "\n";

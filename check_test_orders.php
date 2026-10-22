<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
header('Content-Type: text/plain');

$stmt = $db->query("SELECT id, order_number, customer_name, total_amount, current_status, created_at, updated_at FROM orders ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- WEBHOOK LOGS ---\n";
try {
    $stmt2 = $db->query("SELECT * FROM webhook_logs ORDER BY id DESC LIMIT 5");
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) {
    echo $e->getMessage();
}

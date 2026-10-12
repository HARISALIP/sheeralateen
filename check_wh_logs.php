<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== WEBHOOK LOGS ===\n";
$stmt = $db->query("SELECT id, topic, payload, processed, error_message, received_at FROM webhook_logs WHERE topic = 'leajlak/order_status' ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: " . $r['id'] . " | Date: " . $r['received_at'] . "\n";
    echo "Payload: " . $r['payload'] . "\n\n";
}

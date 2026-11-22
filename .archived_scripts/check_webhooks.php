<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$stmt = $db->query("SELECT * FROM webhook_logs WHERE topic = 'leajlak/order_status' ORDER BY id DESC LIMIT 50");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
if (empty($logs)) {
    echo "No webhooks received yet.";
} else {
    foreach ($logs as $log) {
        echo "====================================\n";
        echo "ID: " . $log['id'] . "\n";
        echo "Time: " . $log['received_at'] . "\n";
        echo "Topic: " . $log['topic'] . "\n";
        echo "Processed: " . $log['processed'] . "\n";
        echo "Error: " . $log['error_message'] . "\n";
        echo "Payload: \n";
        echo $log['payload'] . "\n";
    }
}
echo "</pre>";

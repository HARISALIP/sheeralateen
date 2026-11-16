<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

$logs = $db->query("SELECT id, topic, received_at, payload FROM webhook_logs WHERE topic = 'leajlak/order_status' ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
echo "=== RECENT LEAJLAK WEBHOOK LOGS ===\n";
foreach ($logs as $l) {
    echo "ID: {$l['id']} | Received: {$l['received_at']}\n";
    $p = json_decode($l['payload'], true);
    print_r($p);
    echo "\n----------------------------------------\n";
}

<?php
/**
 * TEMPORARY DIAGNOSTIC SCRIPT
 * Upload to: /public_html/debug_webhook.php
 */
require_once __DIR__ . '/core/bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$db = Database::getConnection();

// Fetch the most recent webhook logs
$stmt = $db->query("SELECT * FROM webhook_logs ORDER BY received_at DESC LIMIT 10");
$logs = $stmt->fetchAll();

if (!$logs) {
    exit("No webhooks found in the database.");
}

header('Content-Type: application/json');

$output = [];
foreach ($logs as $log) {
    $payload = json_decode($log['payload'], true);
    $output[] = [
        'webhook_id' => $log['webhook_id'],
        'topic' => $log['topic'],
        'received_at' => $log['received_at'],
        'order_name' => $payload['name'] ?? 'Unknown',
        'error' => $log['error_message'],
        'note_attributes' => $payload['note_attributes'] ?? 'MISSING_OR_EMPTY'
    ];
}

echo json_encode($output, JSON_PRETTY_PRINT);

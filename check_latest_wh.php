<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$stmt = $db->query("SELECT id, topic, payload, received_at FROM webhook_logs WHERE topic = 'leajlak/order_status' ORDER BY id DESC LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

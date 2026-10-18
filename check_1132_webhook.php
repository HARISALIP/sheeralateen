<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$stmt = $db->query("SELECT id, topic, payload, received_at FROM webhook_logs WHERE payload LIKE '%1132%' ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

$wl = $db->query("SELECT * FROM webhook_logs WHERE topic LIKE '%leajlak%' OR payload LIKE '%payment%' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "=== LEAJLAK WEBHOOK LOGS ===\n";
print_r($wl);

// Also check all distinct topics in webhook_logs
$topics = $db->query("SELECT topic, COUNT(*) as cnt FROM webhook_logs GROUP BY topic")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== TOPICS IN WEBHOOK LOGS ===\n";
print_r($topics);

<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$branches = $db->query("SELECT id, branch_name, branch_code, address, email FROM branches ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($branches as $b) {
    echo "ID: {$b['id']} | Name: {$b['branch_name']} | Addr: {$b['address']}\n";
}

echo "\n=== PAST ACTIVITY LOGS FOR MANAGERS ===\n";
$stmt = $db->query("SELECT action, description, branch_id FROM activity_logs WHERE action LIKE '%manager%' OR description LIKE '%manager%' LIMIT 30");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

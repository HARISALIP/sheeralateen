<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// 1. Who is User 25?
$user25 = $db->query("SELECT id, name, email, role FROM users WHERE id = 25")->fetch(PDO::FETCH_ASSOC);
echo "=== USER 25 ===\n";
print_r($user25);

// 2. Activity logs around 2026-11-16 14:00 - 15:00
$logsToday = $db->query("SELECT * FROM activity_logs WHERE created_at >= '2026-11-16 14:00:00' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== ACTIVITY LOGS TODAY 14:00+ ===\n";
print_r($logsToday);

// 3. Check Order #1147 all activity logs
$logs1147All = $db->query("SELECT a.*, u.name as user_name FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id WHERE a.order_id = 17 ORDER BY a.id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== ALL LOGS FOR ORDER #1147 (id 17) ===\n";
print_r($logs1147All);

<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== USERS (MANAGERS) ===\n";
$stmt = $db->query("SELECT id, name, email, role, status, branch_id FROM users WHERE role = 'branch_manager' ORDER BY id ASC");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== BRANCHES ===\n";
$stmt2 = $db->query("SELECT id, branch_name, branch_code, email, manager_id FROM branches ORDER BY id ASC");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$managers = $db->query("SELECT id, name, email, branch_id FROM users WHERE role = 'branch_manager' AND deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$branches = $db->query("SELECT id, branch_name, branch_code, email, branch_manager_id FROM branches WHERE deleted_at IS NULL ORDER BY branch_name ASC")->fetchAll(PDO::FETCH_ASSOC);

echo "=== ALL BRANCH MANAGERS (" . count($managers) . ") ===\n";
foreach ($managers as $m) {
    echo "Manager ID: {$m['id']} | Name: {$m['name']} | Email: {$m['email']} | Current Branch: {$m['branch_id']}\n";
}

echo "\n=== ALL BRANCHES (" . count($branches) . ") ===\n";
foreach ($branches as $b) {
    echo "Branch ID: {$b['id']} | Name: {$b['branch_name']} | Code: {$b['branch_code']} | Email: {$b['email']} | Current Mgr: {$b['branch_manager_id']}\n";
}

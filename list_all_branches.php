<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$stmt = $db->query("SELECT id, branch_name, branch_code, email, manager_id FROM branches ORDER BY id ASC");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    echo "ID: {$b['id']} | Name: {$b['branch_name']} | Code: {$b['branch_code']} | Email: {$b['email']} | ManagerID: {$b['manager_id']}\n";
}

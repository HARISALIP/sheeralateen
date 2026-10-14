<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$stmt = $db->query("SELECT id, branch_name, branch_code, deleted_at FROM branches WHERE branch_name LIKE '%kamil%' OR branch_name LIKE '%khulais%' OR branch_name LIKE '%hira%' OR deleted_at IS NOT NULL");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

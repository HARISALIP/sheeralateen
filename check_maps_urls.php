<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$branches = $db->query("SELECT id, branch_name, branch_code, maps_url FROM branches WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($branches as $b) {
    echo "ID: {$b['id']} | {$b['branch_name']} | Code: {$b['branch_code']} | URL: {$b['maps_url']}\n";
}

<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("SELECT id, order_number, assigned_branch_id FROM orders ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));


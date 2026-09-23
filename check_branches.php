<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("SELECT id, branch_name, latitude, longitude FROM branches");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));


<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("DESCRIBE system_settings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));


<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("SELECT setting_key FROM system_settings WHERE setting_key LIKE \"%leajlak%\"");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));


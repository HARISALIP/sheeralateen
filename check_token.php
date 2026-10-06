<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = \"leajlak_api_token\"");
echo "Token: " . $stmt->fetchColumn() . "\n";


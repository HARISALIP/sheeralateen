<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();

// Check what is actually stored
$stmt = $db->query("SELECT setting_key, setting_value, LENGTH(setting_value) as len FROM system_settings WHERE setting_key = \"leajlak_api_token\"");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Key: " . $row["setting_key"] . "\n";
echo "Length: " . $row["len"] . "\n";
echo "First 50 chars: " . substr($row["setting_value"], 0, 50) . "\n";
echo "Last 50 chars: " . substr($row["setting_value"], -50) . "\n";
echo "Has whitespace at start: " . (ctype_space(substr($row["setting_value"], 0, 1)) ? "YES" : "NO") . "\n";
echo "Has whitespace at end: " . (ctype_space(substr($row["setting_value"], -1)) ? "YES" : "NO") . "\n";


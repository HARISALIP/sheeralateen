<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'leajlak_webhook_secret'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Webhook secret in DB: " . ($row ? $row['setting_value'] : 'NOT FOUND');

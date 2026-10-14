<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$env = get_setting($db, 'leajlak_environment');
$token = get_setting($db, 'leajlak_api_token');
echo "Current Environment: " . $env . "\n";
echo "Current Token prefix: " . substr($token, 0, 30) . "\n";
echo "Current Token suffix: " . substr($token, -30) . "\n";
echo "Current Token length: " . strlen($token) . "\n";

// Decode JWT payload without verification just to see what sub / aud it has
$parts = explode('.', $token);
if (count($parts) >= 2) {
    echo "Payload: " . base64_decode(strtr($parts[1], '-_', '+/')) . "\n";
}

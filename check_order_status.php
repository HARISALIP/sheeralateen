<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$token = get_setting($db, 'leajlak_api_token', '');
echo "Token configured: " . (!empty($token) ? "YES" : "NO") . "\n";

// Test fetching shops from Leajlak
$ch = curl_init('https://app.leajlak.com/api/partner-v2/shops');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Body:\n" . substr($body, 0, 1000) . "\n";



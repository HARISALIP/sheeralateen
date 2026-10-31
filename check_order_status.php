<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$token = get_setting($db, 'leajlak_api_token', '');

// Test GET /shops/17092
$ch = curl_init('https://app.leajlak.com/api/partner-v2/shops/17092');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "GET /shops/17092 -> Code $code | Body: $body\n";





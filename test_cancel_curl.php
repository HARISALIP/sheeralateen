<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$token = get_setting($db, 'leajlak_api_token');
$env = get_setting($db, 'leajlak_environment');
echo "Env: $env\n";

// Order 1124 UUID from webhook: 1d4bee70-d45a-4bf7-821f-b840a06078f3
$uuid = '1d4bee70-d45a-4bf7-821f-b840a06078f3';
$url = "https://app.leajlak.com/api/partner-v2/orders/$uuid";

echo "Testing POST to $url...\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['cancellation_reason' => 'Test test']));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "POST HTTP Code: $code\nResponse: $body\n\n";

echo "Testing DELETE to $url...\n";
$ch2 = curl_init($url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['cancellation_reason' => 'Test test']));
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$body2 = curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
echo "DELETE HTTP Code: $code2\nResponse: $body2\n";

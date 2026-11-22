<?php
$baseUrl = "https://staging.4ulogistic.com/api/partner-v2";
$payload = json_encode([
    'email' => 'adhil@sheeralateen.com',
    'password' => '4M1sBdx5'
]);

$ch = curl_init($baseUrl . "/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

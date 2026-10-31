<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$token = get_setting($db, 'leajlak_api_token', '');
$ch = curl_init('https://app.leajlak.com/api/partner-v2/shops');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$body = curl_exec($ch);
curl_close($ch);

$shops = json_decode($body, true);
foreach ($shops as $s) {
    echo "ID: {$s['shop_id']} | Name: {$s['name']} | Status: {$s['status']}\n";
}




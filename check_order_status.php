<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
$token = get_setting($db, 'leajlak_api_token', '');

<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$token = get_setting($db, 'leajlak_api_token', '');

// Test sending an order to Leajlak with id '1147(1)' using shop 17084 (Wadi Marikh - active)
// Just as a dry test to see if Leajlak accepts '(' and ')' in the id!
$payload = [
    'id' => '1147(1)',
    'shop_id' => '17092',
    'delivery_details' => [
        'name' => 'Test Razan',
        'phone' => '0558577096',
        'coordinate' => [
            'latitude' => 21.5433,
            'longitude' => 39.1728
        ],
        'address' => 'Jeddah Test'
    ],
    'order' => [
        'payment_type' => 1,
        'total' => 73.00,
        'notes' => 'Test order 1147(1)'
    ]
];

$ch = curl_init('https://app.leajlak.com/api/partner-v2/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $body\n";







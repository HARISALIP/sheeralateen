<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$orderId = 1106; // Based on the user's screenshot it is order number 1106. Wait, in DB is it id=82 or order_number=1106? The URL says id=82. I will search by order_number.

$stmt = $db->prepare("SELECT o.*, b.leajlak_shop_id FROM orders o LEFT JOIN branches b ON o.assigned_branch_id = b.id WHERE o.order_number = :num OR o.id = :num LIMIT 1");
$stmt->execute([':num' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    // If not found by 1106, maybe the id is 82 (from URL)?
    $stmt = $db->prepare("SELECT o.*, b.leajlak_shop_id FROM orders o LEFT JOIN branches b ON o.assigned_branch_id = b.id WHERE o.id = 82 LIMIT 1");
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
    echo "Order not found in DB.";
    exit;
}

$paymentMode = ($order['payment_status'] ?? 'pending') === 'paid' ? '0' : '1';
$clientOrderId = $order['order_number'];
$shopId = $order['leajlak_shop_id'] ?? '821017856';
$customerName = $order['customer_name'] ?? 'Customer';
$customerPhone = $order['customer_phone'] ?? '';
$address = $order['delivery_address'] ?? '';
$amount = (float) ($order['total_amount'] ?? 0);

$formattedAddress = $address;
$decoded = json_decode($address, true);
if (is_array($decoded)) {
    $parts = [];
    if (!empty($decoded['address1'])) $parts[] = $decoded['address1'];
    if (!empty($decoded['address2'])) $parts[] = $decoded['address2'];
    if (!empty($decoded['city'])) $parts[] = $decoded['city'];
    if (!empty($decoded['province'])) $parts[] = $decoded['province'];
    if (!empty($decoded['zip'])) $parts[] = $decoded['zip'];
    if (!empty($decoded['country'])) $parts[] = $decoded['country'];
    $formattedAddress = implode(', ', $parts);
}

$lat = 21.5433;
$lon = 39.1728;
if (preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', trim($formattedAddress))) {
    $parts = explode(',', $formattedAddress);
    $lat = (float)trim($parts[0]);
    $lon = (float)trim($parts[1]);
}

$payload = [
    'id' => preg_replace('/[^A-Za-z0-9]/', '', $clientOrderId),
    'shop_id' => $shopId ?: '821017856',
    'delivery_details' => [
        'name' => $customerName ?: 'Customer',
        'phone' => $customerPhone,
        'coordinate' => [
            'latitude' => $lat,
            'longitude' => $lon
        ],
        'address' => $formattedAddress
    ],
    'order' => [
        'payment_type' => (int)$paymentMode,
        'total' => $amount,
        'notes' => "Order {$clientOrderId}"
    ]
];

echo json_encode($payload, JSON_PRETTY_PRINT);

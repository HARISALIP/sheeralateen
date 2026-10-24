<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
require_once __DIR__ . '/core/GeoService.php';

header('Content-Type: text/plain; charset=utf-8');

// 1. Check order #1142 in orders table
$order = $db->query("SELECT * FROM orders WHERE order_number = '#1142' OR order_number = '1142' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "=== ORDER RECORD ===\n";
print_r($order);

// 2. Check webhook_logs for #1142
$wl = $db->query("SELECT * FROM webhook_logs WHERE topic = 'orders/create' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($wl) {
    echo "\n=== WEBHOOK LOG (ID: {$wl['id']}, received: {$wl['received_at']}) ===\n";
    $p = json_decode($wl['payload'], true);
    echo "Order name in payload: " . ($p['name'] ?? 'N/A') . "\n";
    echo "Customer object:\n";
    print_r($p['customer'] ?? 'NO CUSTOMER');
    echo "Shipping address object:\n";
    print_r($p['shipping_address'] ?? 'NO SHIPPING ADDRESS');
    echo "Billing address object:\n";
    print_r($p['billing_address'] ?? 'NO BILLING ADDRESS');
    echo "Email: " . ($p['email'] ?? 'N/A') . "\n";
    echo "Contact email: " . ($p['contact_email'] ?? 'N/A') . "\n";
    echo "Phone: " . ($p['phone'] ?? 'N/A') . "\n";
    echo "\n=== SHIPPING LINES ===\n";
    print_r($p['shipping_lines'] ?? 'NO SHIPPING LINES');
    echo "\n=== FULFILLMENT ORDERS ===\n";
    print_r($p['fulfillments'] ?? 'NO FULFILLMENTS');
exit;

$stmt = $db->prepare("
    SELECT id, branch_name, branch_code, address, latitude, longitude,
           ROUND(
               6371 * ACOS(
                   COS(RADIANS(:lat)) * COS(RADIANS(latitude))
                   * COS(RADIANS(longitude) - RADIANS(:lng))
                   + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
               ),
           2) AS distance_km
    FROM branches
    WHERE deleted_at IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL
    ORDER BY distance_km ASC
");
$stmt->execute([':lat' => $lat, ':lng' => $lng, ':lat2' => $lat]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Distance from 2779 Al Rabita Al Islamiah (Lat: $lat, Lng: $lng):\n";
echo "-----------------------------------------------------------------------------------------\n";
foreach ($rows as $r) {
    echo sprintf("%-6s | %-25s | %-14s | %6.2f km | Lat: %s, Lng: %s\n",
        $r['id'], $r['branch_name'], $r['branch_code'], $r['distance_km'], $r['latitude'], $r['longitude']);
}
exit;

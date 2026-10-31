<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$addressObj = [
    'address1' => 'الحرازات, حي الاجواد',
    'address2' => '1',
    'city' => 'جدة',
    'province' => '',
    'zip' => '23466',
    'country' => 'Saudi Arabia'
];

echo "Testing geocoding for:\n";
print_r($addressObj);

$coords = GeoService::geocodeShopifyAddress($addressObj);
echo "\nGeocoded Coords:\n";
print_r($coords);

if ($coords) {
    echo "\nDistances to all active branches:\n";
    $stmt = $db->prepare("
        SELECT id, branch_name, branch_code, latitude, longitude,
               ROUND(
                   6371 * ACOS(
                       COS(RADIANS(:lat))  * COS(RADIANS(latitude))
                       * COS(RADIANS(longitude) - RADIANS(:lng))
                       + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
                   ),
               2) AS distance_km
        FROM   branches
        WHERE  status     = 'active'
          AND  deleted_at IS NULL
          AND  latitude   IS NOT NULL
          AND  longitude  IS NOT NULL
        ORDER  BY distance_km ASC
    ");
    $stmt->execute([':lat' => $coords['lat'], ':lng' => $coords['lng'], ':lat2' => $coords['lat']]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($branches as $b) {
        echo "{$b['branch_name']} ({$b['branch_code']}): {$b['distance_km']} km | Lat: {$b['latitude']}, Lng: {$b['longitude']}\n";
    }

    $nearestId = GeoService::findNearestBranch($db, $coords['lat'], $coords['lng']);
    echo "\nNearest Branch ID: $nearestId\n";
} else {
    echo "\nGeocoding returned NULL!\n";
}

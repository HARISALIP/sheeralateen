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

echo "Testing GeoService::geocodeShopifyAddress with:\n";
print_r($addressObj);

$coords = GeoService::geocodeShopifyAddress($addressObj);
echo "\nResolved Coordinates:\n";
print_r($coords);

if ($coords) {
    $nearestId = GeoService::findNearestBranch($db, $coords['lat'], $coords['lng']);
    
    $stmt = $db->prepare("SELECT id, branch_name, branch_code FROM branches WHERE id = :id");
    $stmt->execute([':id' => $nearestId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n--> Assigned Nearest Branch:\n";
    print_r($branch);
} else {
    echo "\nFailed to resolve coords\n";
}




<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

$lat = 21.4923665;
$lng = 39.3664168;

echo "Coordinates for Al Harazat: $lat, $lng\n\n";

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
$stmt->execute([':lat' => $lat, ':lng' => $lng, ':lat2' => $lat]);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($branches as $b) {
    echo "{$b['branch_name']} ({$b['branch_code']}): {$b['distance_km']} km\n";
}

$nearestId = GeoService::findNearestBranch($db, $lat, $lng);
echo "\n--> Nearest branch ID: $nearestId\n";



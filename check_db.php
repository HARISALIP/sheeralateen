<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
header('Content-Type: text/plain; charset=utf-8');

$lat = 21.5385; // Al Rabita Al Islamiah, Bani Malik / Mishrifah
$lng = 39.2019;

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

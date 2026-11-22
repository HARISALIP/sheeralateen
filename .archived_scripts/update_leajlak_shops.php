<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$csv = <<<CSV
Name,Shop id,Location,Status
Shiralateen-Al Ajaweed,17073,"21.4089274, 39.2741561",active
Shiralateen-Al Samir 2,17074,"21.5913034, 39.1983544",active
Shiralateen-Al Samir-3,17075,"21.5145988, 39.7848358",active
Shiralateen-Al-Naseem,17076,"21.5169399, 39.2238201",active
Shiralateen-Hamdaniya,17077,"21.746645259306, 39.1808608829024",active
Shiralateen-Khuwaisa-2,17078,"21.4982087, 39.2540836",active
Shiralateen-Lulu - Ameer Fawas ,17079,"21.4378469, 39.2800342",active
Shiralateen-Lulu-Jamia,17080,"21.4819648732726, 39.2377570804151",active
Shiralateen-Lulu-Ruwais,17081,"21.5171909, 39.1654283",active
Shiralateen-Al Marwa,17082,"21.6074267, 39.1925653",active
Shiralateen-Safa,17083,"21.5717538, 39.2203089",active
Shiralateen-Wadi Marikh,17084,"21.5568591, 39.2538832",active
Shiralateen-Kakia,17085,"21.3725873, 39.7935773",active
Shiralateen-Sanabel,17086,"21.410663, 39.2671003",active
Shiralateen-Al-Naeem,17087,"21.6181551, 39.1405162",active
Shiralateen-Harasath 2,17088,"21.4819453, 39.3353789",inactive
Shiralateen-Al Qarnia,17089,"21.3003756, 39.2359937",inactive
Shiralateen-Bahra,17090,"21.4014449, 39.4505122",inactive
Shiralateen-Fadeylah,17091,"21.310416, 39.2651532",inactive
Shiralateen-Harasath 1,17092,"21.4819453, 39.3353789",inactive
Shiralateen-Muhameed,17093,"21.442582, 39.3807062",inactive
Shiralateen-Al-Riyadh (Osfan),17094,"21.8786154, 39.1112229",inactive
Shiralateen-Jumoom,17095,"21.5994956, 39.6199392",inactive
Shiralateen-Gharan,17096,"21.9755077, 39.3616149",inactive
Shiralateen-Al-Reheli,17097,"21.7981714, 39.1394921",active
Shiralateen-Obhur,17098,"21.7889248, 39.0953094",active
Shiralateen-Thayseer,17099,"21.5519371, 39.2787517",inactive
Shiralateen-Makkah-Taneem,17100,"21.5145988, 39.7848358",inactive
CSV;

$lines = explode("\n", trim($csv));
array_shift($lines); // remove header

$stmtName = $db->prepare("UPDATE branches SET leajlak_shop_id = :shop_id WHERE branch_name = :name OR branch_name LIKE :like_name");
$stmtCoords = $db->prepare("UPDATE branches SET leajlak_shop_id = :shop_id WHERE ROUND(latitude, 5) = ROUND(:lat, 5) AND ROUND(longitude, 5) = ROUND(:lng, 5)");

echo "<div style='font-family: monospace; line-height: 1.6;'>";
echo "<h3>Updating Leajlak Shop IDs...</h3><ul>";

$successCount = 0;
foreach ($lines as $line) {
    $parts = str_getcsv($line);
    if (count($parts) < 4) continue;
    
    $rawName = trim($parts[0]);
    $shopId = trim($parts[1]);
    $location = trim($parts[2]); // "lat, lng"
    
    $branchName = str_replace('Shiralateen-', '', $rawName);
    $branchName = trim($branchName);
    
    $locParts = explode(',', $location);
    $lat = isset($locParts[0]) ? trim($locParts[0]) : 0;
    $lng = isset($locParts[1]) ? trim($locParts[1]) : 0;
    
    $updated = false;
    
    // Attempt exact or LIKE name match first
    $stmtName->execute([
        ':shop_id' => $shopId, 
        ':name' => $branchName,
        ':like_name' => "%" . $branchName . "%"
    ]);
    if ($stmtName->rowCount() > 0) {
        $updated = true;
    }
    
    // Fallback to coordinate match
    if (!$updated && $lat && $lng) {
        $stmtCoords->execute([
            ':shop_id' => $shopId,
            ':lat' => (float)$lat,
            ':lng' => (float)$lng
        ]);
        if ($stmtCoords->rowCount() > 0) {
            $updated = true;
        }
    }
    
    if ($updated) {
        echo "<li><strong style='color: green;'>SUCCESS:</strong> {$branchName} -> Shop ID: {$shopId}</li>";
        $successCount++;
    } else {
        echo "<li><strong style='color: red;'>FAILED:</strong> Could not match branch {$branchName} in DB</li>";
    }
}

echo "</ul><hr><b>Total shops linked: {$successCount}</b></div>";

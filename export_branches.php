<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT branch_name, branch_code, latitude, longitude, phone FROM branches WHERE deleted_at IS NULL");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Branch List:\n\n";
foreach ($branches as $b) {
    echo "Name: " . $b['branch_name'] . " (" . $b['branch_code'] . ")\n";
    echo "Latitude: " . ($b['latitude'] ?? 'N/A') . "\n";
    echo "Longitude: " . ($b['longitude'] ?? 'N/A') . "\n";
    echo "Phone: " . ($b['phone'] ?? 'N/A') . "\n";
    echo "-------------------------\n";
}

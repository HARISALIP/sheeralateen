<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/GeoService.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// 1. Get all active branches with coordinates
$activeBranches = $db->query("SELECT id, branch_name, branch_code, status, latitude, longitude FROM branches WHERE status = 'active' AND deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "=== ACTIVE BRANCHES (" . count($activeBranches) . ") ===\n";
foreach ($activeBranches as $b) {
    echo "ID: {$b['id']} | {$b['branch_name']} ({$b['branch_code']}) | Lat: {$b['latitude']}, Lng: {$b['longitude']}\n";
}

// 2. Check all recent orders (e.g. latest 25 orders)
$orders = $db->query("
    SELECT o.id, o.order_number, o.shopify_order_id, o.customer_name, o.customer_phone, 
           o.delivery_address, o.assigned_branch_id, o.current_status, o.created_at,
           b.branch_name as assigned_branch_name, b.status as assigned_branch_status,
           b.latitude as branch_lat, b.longitude as branch_lng
    FROM orders o
    LEFT JOIN branches b ON o.assigned_branch_id = b.id
    ORDER BY o.id DESC LIMIT 25
")->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== LATEST " . count($orders) . " ORDERS LOCATION AUDIT ===\n\n";

foreach ($orders as $o) {
    $orderNum = $o['order_number'];
    $assignedName = $o['assigned_branch_name'] ?: 'UNASSIGNED';
    $assignedStatus = $o['assigned_branch_status'] ?: 'N/A';
    
    // Check if coordinates exist in Shopify webhook payload
    $lat = null;
    $lng = null;
    $source = 'none';

    if (!empty($o['shopify_order_id'])) {
        $wl = $db->query("SELECT payload FROM webhook_logs WHERE topic = 'orders/create' AND payload LIKE '%" . $o['shopify_order_id'] . "%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($wl) {
            $p = json_decode($wl['payload'], true);
            if (!empty($p['shipping_address']['latitude']) && !empty($p['shipping_address']['longitude'])) {
                $lat = (float)$p['shipping_address']['latitude'];
                $lng = (float)$p['shipping_address']['longitude'];
                $source = 'shopify_shipping_address';
            }
        }
    }

    // If no coordinates in webhook payload, try geocoding
    if (!$lat || !$lng) {
        $addrObj = json_decode($o['delivery_address'], true);
        if (is_array($addrObj)) {
            $geo = GeoService::geocodeShopifyAddress($addrObj);
            if ($geo) {
                $lat = $geo['lat'];
                $lng = $geo['lng'];
                $source = 'geocoded_address';
            }
        }
    }

    echo "--------------------------------------------------------------------------------\n";
    echo "Order: {$orderNum} (ID: {$o['id']}) | Created: {$o['created_at']} | Status: {$o['current_status']}\n";
    echo "Customer: {$o['customer_name']} | Address: " . (is_string($o['delivery_address']) ? $o['delivery_address'] : json_encode($o['delivery_address'])) . "\n";
    echo "Coordinates: " . ($lat ? "Lat: {$lat}, Lng: {$lng} [Source: {$source}]" : "COULD NOT RESOLVE GPS") . "\n";
    echo "Currently Assigned: {$assignedName} (ID: {$o['assigned_branch_id']}) [Branch Status: {$assignedStatus}]\n";

    if ($lat && $lng) {
        // Find nearest active branch via SQL Haversine
        $nearestId = GeoService::findNearestBranch($db, $lat, $lng);
        
        // Also list top 3 nearest active branches with distances
        $stmtDist = $db->prepare("
            SELECT id, branch_name, branch_code, status,
                   ROUND(6371 * ACOS(
                       COS(RADIANS(:lat)) * COS(RADIANS(latitude))
                       * COS(RADIANS(longitude) - RADIANS(:lng))
                       + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
                   ), 2) AS distance_km
            FROM branches
            WHERE status = 'active' AND deleted_at IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL
            ORDER BY distance_km ASC
            LIMIT 3
        ");
        $stmtDist->execute([':lat' => $lat, ':lng' => $lng, ':lat2' => $lat]);
        $topBranches = $stmtDist->fetchAll(PDO::FETCH_ASSOC);

        $nearestBranch = $topBranches[0] ?? null;
        $isMatch = ($nearestBranch && (int)$nearestBranch['id'] === (int)$o['assigned_branch_id']);

        if ($isMatch) {
            echo "Result: [OK - CORRECT] Nearest active branch is {$nearestBranch['branch_name']} ({$nearestBranch['distance_km']} km)\n";
        } else {
            echo "Result: [MISMATCH / REVIEW NEEDED]\n";
            echo "   Assigned: {$assignedName} (ID: {$o['assigned_branch_id']})\n";
            echo "   Should be nearest active: " . ($nearestBranch ? "{$nearestBranch['branch_name']} (ID: {$nearestBranch['id']}, {$nearestBranch['distance_km']} km)" : "None") . "\n";
        }

        echo "Top Nearest Active Branches:\n";
        foreach ($topBranches as $tb) {
            echo "   - {$tb['branch_name']} (ID: {$tb['id']}): {$tb['distance_km']} km\n";
        }
    } else {
        echo "Result: [CANNOT EVALUATE DISTANCE - NO GPS]\n";
    }
}

<?php
/**
 * database/auto_sync_shopify_locations.php
 * Fetches all 10 live Shopify locations via API and automatically
 * links their IDs into the branches table by matching branch names.
 */
require_once __DIR__ . '/../core/bootstrap.php';

$db = Database::getConnection();
$shopify = new ShopifyService($db);

echo "1. Connecting to Shopify API to fetch locations...\n";
try {
    $res = $shopify->getLocations();
    $locations = $res['locations'] ?? [];
    echo "   Found " . count($locations) . " active locations in Shopify.\n";

    if (empty($locations)) {
        die("No locations returned from Shopify API. Check API credentials.\n");
    }

    // Mapping dictionary: Shopify location name pattern -> branch_code in DB
    $mapRules = [
        'Ajaweed'      => 'ALAJAWEED',
        'Qarnia'       => 'ALQARNIA',
        'Samir'        => 'ALSAMIR2',
        'Naeem'        => 'ALNAEEM',
        'Hamadaniyyah' => 'HAMDANIYA',
        'Harasath'     => 'HARASATH1',
        'Kuwaisa'      => 'KHUWAISA2',
        'Lulu'         => 'LULURUWAIS',
        'Safa'         => 'SAFA',
        'Riyadh'       => 'ALRIYADHOSFAN'
    ];

    $matched = 0;
    $upd = $db->prepare("UPDATE branches SET shopify_location_id = :loc_id WHERE branch_code = :bcode");

    foreach ($locations as $loc) {
        $locId = (int)$loc['id'];
        $locName = $loc['name'];
        $foundCode = null;

        foreach ($mapRules as $pattern => $code) {
            if (stripos($locName, $pattern) !== false) {
                $foundCode = $code;
                break;
            }
        }

        if ($foundCode) {
            $upd->execute([':loc_id' => $locId, ':bcode' => $foundCode]);
            echo "   [MATCHED] '{$locName}' (ID: {$locId}) -> Branch Code '{$foundCode}'\n";
            $matched++;
        } else {
            echo "   [SKIPPED] '{$locName}' (No rule defined)\n";
        }
    }

    echo "\n2. Result: Successfully linked $matched of " . count($locations) . " Shopify locations to your branches!\n";
    echo "DONE!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

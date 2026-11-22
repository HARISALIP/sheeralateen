<?php
/**
 * database/fix_existing_order_timestamps.php
 * ---------------------------------------------------------
 * Connects to Shopify API and corrects created_at timestamps for all
 * existing orders in the database to exact Asia/Riyadh (Jeddah local time).
 */
require_once __DIR__ . '/../core/bootstrap.php';

$db = Database::getConnection();
$shopify = new ShopifyService($db);

echo "1. Fetching orders from database...\n";
$orders = $db->query("SELECT id, order_number, shopify_order_id, created_at FROM orders WHERE shopify_order_id IS NOT NULL ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

echo "   Found " . count($orders) . " Shopify orders in database to inspect.\n";

$upd = $db->prepare("UPDATE orders SET created_at = :cat WHERE id = :id");
$fixed = 0;

foreach ($orders as $o) {
    try {
        $spOrder = $shopify->getOrder($o['shopify_order_id']);
        $rawCreatedAt = $spOrder['order']['created_at'] ?? null;

        if ($rawCreatedAt) {
            $dt = new DateTime($rawCreatedAt);
            $dt->setTimezone(new DateTimeZone('Asia/Riyadh'));
            $jeddahTime = $dt->format('Y-m-d H:i:s');

            if ($jeddahTime !== $o['created_at']) {
                $upd->execute([':cat' => $jeddahTime, ':id' => $o['id']]);
                echo "   [CORRECTED] Order {$o['order_number']}: {$o['created_at']} -> {$jeddahTime} (Shopify raw: {$rawCreatedAt})\n";
                $fixed++;
            }
        }
    } catch (Exception $e) {
        echo "   [SKIP] Order {$o['order_number']}: " . $e->getMessage() . "\n";
    }
}

// Also ensure system_settings has Asia/Riyadh timezone saved
save_setting($db, 'timezone', 'Asia/Riyadh');

echo "\n2. Summary: Successfully corrected $fixed order timestamps to Asia/Riyadh (Jeddah) timezone.\n";
echo "DONE!\n";

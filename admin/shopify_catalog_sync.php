<?php
/**
 * admin/shopify_catalog_sync.php
 * ---------------------------------------------------------
 * Fetches all products and variants directly from Shopify REST Admin API
 * and syncs them into products_catalog (inserting new products & updating prices/images).
 * Also ensures every branch has inventory records for any new products.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();
$shopify = new ShopifyService($db);

$stats = [
    'fetched'  => 0,
    'imported' => 0,
    'updated'  => 0,
    'errors'   => []
];

if (!$shopify->isConfigured()) {
    flash('error', 'Shopify API is not configured.');
    header('Location: inventory.php');
    exit;
}

try {
    // 1. Fetch products from Shopify
    // Request up to 250 products
    $reflection = new ReflectionClass($shopify);
    $requestMethod = $reflection->getMethod('request');
    $requestMethod->setAccessible(true);

    $response = $requestMethod->invoke($shopify, 'GET', '/products.json?limit=250');
    $shopifyProducts = $response['products'] ?? [];
    $stats['fetched'] = count($shopifyProducts);

    $stmtUpsert = $db->prepare("
        INSERT INTO products_catalog (
            shopify_product_id, shopify_variant_id, shopify_inventory_item_id,
            handle, title, variant_title, sku, price, image_url, status, updated_at
        ) VALUES (
            :spid, :svid, :siid,
            :handle, :title, :vtitle, :sku, :price, :img, :status, NOW()
        )
        ON DUPLICATE KEY UPDATE
            shopify_product_id        = VALUES(shopify_product_id),
            shopify_variant_id        = VALUES(shopify_variant_id),
            shopify_inventory_item_id = VALUES(shopify_inventory_item_id),
            title                     = VALUES(title),
            variant_title             = VALUES(variant_title),
            sku                       = VALUES(sku),
            price                     = VALUES(price),
            image_url                 = IF(VALUES(image_url) != '', VALUES(image_url), image_url),
            status                    = VALUES(status),
            updated_at                = NOW()
    ");

    $branches = $db->query("SELECT id FROM branches WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    $invStmt = $db->prepare("
        INSERT IGNORE INTO branch_inventory (branch_id, product_id, available_quantity, on_hand_quantity)
        VALUES (:bid, :pid, 20, 20)
    ");

    foreach ($shopifyProducts as $sp) {
        $variants = $sp['variants'] ?? [];
        $imgUrl = !empty($sp['image']['src']) ? $sp['image']['src'] : (!empty($sp['images'][0]['src']) ? $sp['images'][0]['src'] : '');
        $status = ($sp['status'] ?? 'active') === 'active' ? 'active' : 'unlisted';

        foreach ($variants as $v) {
            $handle = $sp['handle'];
            if (count($variants) > 1 && !empty($v['title']) && $v['title'] !== 'Default Title') {
                $handle .= '-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($v['title']));
            }

            $stmtUpsert->execute([
                ':spid'    => (int)$sp['id'],
                ':svid'    => (int)$v['id'],
                ':siid'    => (int)($v['inventory_item_id'] ?? 0),
                ':handle'  => $handle,
                ':title'   => $sp['title'],
                ':vtitle'  => $v['title'] ?? 'Default Title',
                ':sku'     => $v['sku'] ?? null,
                ':price'   => (float)($v['price'] ?? 0.00),
                ':img'     => $imgUrl,
                ':status'  => $status
            ]);

            // Get product_id
            $pid = (int)$db->lastInsertId();
            if ($pid === 0) {
                $find = $db->prepare("SELECT id FROM products_catalog WHERE handle = :handle LIMIT 1");
                $find->execute([':handle' => $handle]);
                $pid = (int)$find->fetchColumn();
                $stats['updated']++;
            } else {
                $stats['imported']++;
            }

            // Ensure all branches have a record for this product
            if ($pid > 0) {
                foreach ($branches as $bid) {
                    $invStmt->execute([':bid' => $bid, ':pid' => $pid]);
                }
            }
        }
    }

    ActivityLogger::log((int)$_SESSION['user_id'], 'shopify_catalog_synced', "Synced {$stats['fetched']} products from Shopify");
    flash('success', "Catalog sync complete! Fetched {$stats['fetched']} products from Shopify.");

} catch (Exception $e) {
    flash('error', "Catalog sync failed: " . $e->getMessage());
}

header('Location: inventory.php');
exit;

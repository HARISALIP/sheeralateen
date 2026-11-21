<?php
/**
 * database/seed_products_from_csv.php
 * Seeds products_catalog from the store's Shopify product export.
 * Also initializes branch_inventory for all active branches.
 */
require_once __DIR__ . '/../core/bootstrap.php';

$db = Database::getConnection();

echo "1. Applying migration if tables do not exist...\n";
$sql = file_get_contents(__DIR__ . '/inventory_migration.sql');
$db->exec($sql);
echo "   Tables verified / created successfully.\n";

echo "2. Seeding products into products_catalog...\n";
$csvPath = __DIR__ . '/../products_export.csv';

// Let's check products from the active catalog
$products = [
    ['handle' => 'pettit-four', 'title' => 'Pettit Four', 'price' => 44.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/PetitFour.jpg', 'status' => 'active'],
    ['handle' => 'moovala-biscute', 'title' => 'Moovala Biscut', 'price' => 49.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/MoovalaBiscut.jpg', 'status' => 'active'],
    ['handle' => 'minifathayir', 'title' => 'Minifathayir', 'price' => 44.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/MiniFathayirMedium.jpg', 'status' => 'active'],
    ['handle' => 'crunchy-coffee', 'title' => 'Crunchy Coffee', 'price' => 45.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/CrunchyCoffee.jpg', 'status' => 'active'],
    ['handle' => 'delight-cake', 'title' => 'Delight Cake', 'price' => 74.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/DelightCake.jpg', 'status' => 'active'],
    ['handle' => 'hala-mix-big', 'title' => 'Hala Mix Big', 'price' => 109.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/HalaMixBig.jpg', 'status' => 'active'],
    ['handle' => 'pizza-saudi', 'title' => 'Pizza Saudi', 'price' => 29.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/AsabaChocolate.jpg', 'status' => 'active'],
    ['handle' => 'varganab', 'title' => 'Varganab', 'price' => 26.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/Varakenab.jpg', 'status' => 'active'],
    ['handle' => 'trufle-flake', 'title' => 'Trufle Flake', 'price' => 7.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/TrifleFlake_9a072f93-6e1e-49b2-8e94-4c50ce365fea.jpg', 'status' => 'active'],
    ['handle' => 'chocolate-baby-boy', 'title' => 'Chocolate Baby Boy', 'price' => 115.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/ChocolateBabyBoy115.jpg', 'status' => 'active'],
    ['handle' => 'chocolate-baby-girl', 'title' => 'Chocolate Baby Girl', 'price' => 115.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/ChocolateBabyGirl115.jpg', 'status' => 'active'],
    ['handle' => 'chocolate-cracker', 'title' => 'Chocolate Cracker', 'price' => 99.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/ChcocolateCrackerStick.jpg', 'status' => 'active'],
    ['handle' => 'huzel-rocher', 'title' => 'Hazel Rocher', 'price' => 49.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/HuzelRocher_ca30b23b-430d-4b08-a711-b073b56b3f8b.jpg', 'status' => 'active'],
    ['handle' => 'hala-noora', 'title' => 'Hala Noora', 'price' => 49.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/HalaNoora_cc267141-4db0-4b18-9329-0a67728c762e.jpg', 'status' => 'active'],
    ['handle' => 'belgium-biscuit-1', 'title' => 'Belgium Biscuit', 'price' => 29.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/BelgiumBiscut_5f0c50db-3ce5-4893-ab3d-188e7241a361.jpg', 'status' => 'active'],
    ['handle' => 'baglawa-mix-medium', 'title' => 'Baglawa Mix Small', 'price' => 59.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/WhatsApp_Image_2026-09-05_at_12.25.39_PM_2.jpg', 'status' => 'active'],
    ['handle' => 'baglawa-mix-big', 'title' => 'Baglawa Mix Big', 'price' => 99.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/WhatsApp_Image_2026-09-05_at_12.25.39_PM_1.jpg', 'status' => 'active'],
    ['handle' => 'oreo-cappuchino', 'title' => 'Oreo Cappuchino', 'price' => 41.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/OreoCappuchino.jpg', 'status' => 'active'],
    ['handle' => 'petit-four-francy', 'title' => 'Petit Four Francy', 'price' => 49.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/PetitFourFrancy.jpg', 'status' => 'active'],
    ['handle' => 'basboosa-almond', 'title' => 'Basboosa Almond', 'price' => 29.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/BasboosaAlmond.jpg', 'status' => 'active'],
    ['handle' => 'mamoul-burr', 'title' => 'Mamoul Burr', 'price' => 25.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/MamoulBurr.jpg', 'status' => 'active'],
    ['handle' => 'cake-almani', 'title' => 'Cake Almani', 'price' => 39.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/CakeAlmani.jpg', 'status' => 'active'],
    ['handle' => 'hala-magrabi', 'title' => 'Hala Magrabi', 'price' => 49.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/HalaMagrabi.jpg', 'status' => 'active'],
    ['handle' => 'snicker-bar', 'title' => 'Snicker Bar', 'price' => 45.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/SnickerBar.jpg', 'status' => 'active'],
    ['handle' => 'hala-haliya', 'title' => 'Hala Haliya', 'price' => 54.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/HalaHaliya.jpg', 'status' => 'active'],
    ['handle' => 'basboosa-kinder', 'title' => 'Basboosa Kinder', 'price' => 29.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/KinderBasboosa.jpg', 'status' => 'active'],
    ['handle' => 'luqeemath', 'title' => 'Luqeemath', 'price' => 30.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/Luqeemath.jpg', 'status' => 'active'],
    ['handle' => 'cake-bundook', 'title' => 'Cake Bundook', 'price' => 53.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/BundookCake.jpg', 'status' => 'active'],
    ['handle' => 'galaxy-chocolate', 'title' => 'Galaxy Chocolate', 'price' => 39.00, 'image_url' => 'https://cdn.shopify.com/s/files/1/0788/1038/2591/files/GalaxyChocolate.jpg', 'status' => 'active'],
    ['handle' => 'adeeb-cake', 'title' => 'Adeeb Cake', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'honey-cake', 'title' => 'Honey Cake', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'reem', 'title' => 'Reem', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'english-cake-tamar', 'title' => 'English Cake Tamar', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'english-cake-dukhun', 'title' => 'English Cake Dukhun', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'baby-chocolates', 'title' => 'Baby Chocolates', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'trifles', 'title' => 'Trifles', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'kunafa-chocolate', 'title' => 'Kunafa Chocolate', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'premium-chocolate', 'title' => 'Premium Chocolate', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'basboosa-mix', 'title' => 'Basboosa Mix', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'chocolate-with-siniya-gift-tray', 'title' => 'Chocolate with Siniya (Gift Tray)', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'chocolate-francy', 'title' => 'Chocolate Francy', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'kunafa-pistachio-cake', 'title' => 'Kunafa Pistachio Cake', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'printed-chocolate-bokeh', 'title' => 'Printed Chocolate Bokeh', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'mamool-tamar', 'title' => 'Mamool Tamar', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'custom-cakes-for-every-occasion', 'title' => 'Custom Cakes for Every Occasion', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'baglawa', 'title' => 'Baglawa', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'biscuit-nuts', 'title' => 'Biscuit Nuts', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'english-cake-chocolate', 'title' => 'English Cake Chocolate', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'almani-mix', 'title' => 'Almani Mix', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
    ['handle' => 'luqimath-jubin', 'title' => 'Luqimath Jubin', 'price' => 0.00, 'image_url' => '', 'status' => 'unlisted'],
];

$stmt = $db->prepare("
    INSERT INTO products_catalog (handle, title, price, image_url, status)
    VALUES (:handle, :title, :price, :image_url, :status)
    ON DUPLICATE KEY UPDATE 
        title = VALUES(title),
        price = VALUES(price),
        image_url = VALUES(image_url),
        status = VALUES(status)
");

$seededCount = 0;
foreach ($products as $p) {
    $stmt->execute([
        ':handle'    => $p['handle'],
        ':title'     => $p['title'],
        ':price'     => $p['price'],
        ':image_url' => $p['image_url'],
        ':status'    => $p['status']
    ]);
    $seededCount++;
}
echo "   Successfully upserted $seededCount products into catalog.\n";

echo "3. Initializing branch_inventory records for all active branches...\n";
$branches = $db->query("SELECT id, branch_name FROM branches WHERE deleted_at IS NULL")->fetchAll();
$catalogItems = $db->query("SELECT id FROM products_catalog")->fetchAll();

$invStmt = $db->prepare("
    INSERT IGNORE INTO branch_inventory (branch_id, product_id, available_quantity, on_hand_quantity)
    VALUES (:bid, :pid, 20, 20)
");

$linksCreated = 0;
foreach ($branches as $b) {
    foreach ($catalogItems as $c) {
        $invStmt->execute([
            ':bid' => $b['id'],
            ':pid' => $c['id']
        ]);
        if ($invStmt->rowCount() > 0) {
            $linksCreated++;
        }
    }
}
echo "   Successfully initialized branch inventory slots (Created $linksCreated new records across " . count($branches) . " branches).\n";
echo "DONE!\n";

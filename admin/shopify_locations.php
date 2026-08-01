<?php
/**
 * admin/shopify_locations.php
 * Temporary script to view Shopify Locations for setup purposes.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();
$shopify = new ShopifyService($db);

$locationsData = [];
$errorMsg = null;

try {
    $response = $shopify->getLocations();
    $locationsData = $response['locations'] ?? [];
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
}

$pageTitle = 'Shopify Locations (Setup)';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Shopify Locations Map</h1>
        <p class="page-subtitle">Use these IDs to populate the Shopify Location ID fields in your Branches.</p>
    </div>
</div>

<?php if ($errorMsg): ?>
<div class="alert alert-danger">
    <i class="fa-solid fa-triangle-exclamation"></i>
    Failed to fetch locations: <?= htmlspecialchars($errorMsg) ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Available Locations</h2>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Location ID</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>City</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locationsData as $loc): ?>
                <tr>
                    <td>
                        <code style="background:var(--bg-main);padding:4px 8px;border-radius:4px;font-size:14px;cursor:pointer;" onclick="navigator.clipboard.writeText('<?= $loc['id'] ?>'); alert('Copied: <?= $loc['id'] ?>');" title="Click to copy">
                            <?= $loc['id'] ?>
                        </code>
                    </td>
                    <td><strong><?= htmlspecialchars($loc['name']) ?></strong></td>
                    <td><?= htmlspecialchars($loc['address1'] . ($loc['address2'] ? ', ' . $loc['address2'] : '')) ?></td>
                    <td><?= htmlspecialchars($loc['city']) ?></td>
                    <td>
                        <?php if ($loc['active']): ?>
                            <span class="status active">Active</span>
                        <?php else: ?>
                            <span class="status inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($locationsData)): ?>
                <tr>
                    <td colspan="5" class="empty-state">No locations found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

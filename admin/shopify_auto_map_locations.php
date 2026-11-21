<?php
/**
 * admin/shopify_auto_map_locations.php
 * ---------------------------------------------------------
 * Matches live Shopify Locations with branches in the OMS database.
 * Super Admin tool to auto-bind the 10 Shopify Locations to local branches.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();
$shopify = new ShopifyService($db);

$locations = [];
$errorMsg = null;
$mappedCount = 0;
$log = [];

try {
    $response = $shopify->getLocations();
    $locations = $response['locations'] ?? [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mapping') {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'CSRF token mismatch.');
            header('Location: shopify_auto_map_locations.php');
            exit;
        }

        $mappings = $_POST['map'] ?? []; // [branch_id => location_id]
        
        $upd = $db->prepare("UPDATE branches SET shopify_location_id = :loc_id WHERE id = :bid");
        $clr = $db->prepare("UPDATE branches SET shopify_location_id = NULL WHERE shopify_location_id = :loc_id");

        foreach ($mappings as $branchId => $locId) {
            $branchId = (int)$branchId;
            $locId = !empty($locId) ? (int)$locId : null;

            if ($locId) {
                // Clear any other branch holding this location ID to respect UNIQUE constraint
                $clr->execute([':loc_id' => $locId]);
                $upd->execute([':loc_id' => $locId, ':bid' => $branchId]);
                $mappedCount++;
            } else {
                $upd->execute([':loc_id' => null, ':bid' => $branchId]);
            }
        }

        flash('success', "Updated Shopify Location mappings successfully ($mappedCount branches mapped).");
        header('Location: shopify_auto_map_locations.php');
        exit;
    }

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
}

// Fetch all branches
$branches = $db->query("SELECT id, branch_name, branch_code, shopify_location_id FROM branches WHERE deleted_at IS NULL ORDER BY branch_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Map Shopify Locations';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">Shopify Location Mapping</h1>
        <p class="page-subtitle">Bind your active Shopify Locations to your physical OMS branches.</p>
    </div>
    <div>
        <a href="inventory.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Back to Inventory</a>
    </div>
</div>

<?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger">
    <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?>
</div>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Shopify Locations (Found <?= count($locations) ?> in Shopify)</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="shopify_auto_map_locations.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_mapping">

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>OMS Physical Branch</th>
                            <th>Branch Code</th>
                            <th>Assigned Shopify Location</th>
                            <th>Current Sync Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($branches as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($b['branch_code']) ?></code></td>
                            <td>
                                <select name="map[<?= $b['id'] ?>]" class="form-control" style="max-width:320px;">
                                    <option value="">-- No Shopify Location (Local OMS Stock Only) --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <?php 
                                            $selected = ((string)$b['shopify_location_id'] === (string)$loc['id']);
                                            // Soft auto-match hint if not set
                                            $autoMatch = !$b['shopify_location_id'] && (stripos($loc['name'], $b['branch_name']) !== false || stripos($loc['name'], $b['branch_code']) !== false);
                                        ?>
                                        <option value="<?= $loc['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($loc['name']) ?> (ID: <?= $loc['id'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php if (!empty($b['shopify_location_id'])): ?>
                                    <span class="status completed"><i class="fa-solid fa-link"></i> Mapped (<?= $b['shopify_location_id'] ?>)</span>
                                <?php else: ?>
                                    <span class="status inactive">Local Only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Save Location Mappings</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

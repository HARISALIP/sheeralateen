<?php
/**
 * api/branches.php
 * ---------------------------------------------------------
 * Public API: Returns all active branches with full details.
 * Used by Shopify storefront widget for map initialisation
 * and dropdown population.
 *
 * GET /api/branches.php           — all branches, alpha order
 * GET /api/branches.php?q=jeddah  — filtered by name / city
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=300'); // 5-minute cache

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    $db = Database::getConnection();

    $sql = "
        SELECT
            id,
            branch_code,
            branch_name AS name,
            address,
            phone,
            maps_url,
            latitude,
            longitude
        FROM  branches
        WHERE status    = 'active'
          AND deleted_at IS NULL
    ";

    if ($search !== '') {
        $sql .= " AND (branch_name LIKE :search OR address LIKE :search2) ";
    }

    $sql .= " ORDER BY branch_name ASC";

    $stmt = $db->prepare($sql);

    if ($search !== '') {
        $param = '%' . $search . '%';
        $stmt->bindValue(':search',  $param, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $param, PDO::PARAM_STR);
    }

    $stmt->execute();
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast types
    foreach ($branches as &$b) {
        $b['id']        = (int)   $b['id'];
        $b['latitude']  = $b['latitude']  !== null ? (float) $b['latitude']  : null;
        $b['longitude'] = $b['longitude'] !== null ? (float) $b['longitude'] : null;
    }
    unset($b);

    echo json_encode([
        'status' => 'success',
        'count'  => count($branches),
        'data'   => $branches,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

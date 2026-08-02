<?php
/**
 * api/nearest_branches.php
 * ---------------------------------------------------------
 * Public API: Returns active branches sorted by proximity.
 *
 * Usage:
 *   GET /api/nearest_branches.php?lat=21.38&lng=39.85
 *   GET /api/nearest_branches.php?lat=21.38&lng=39.85&limit=5
 *   GET /api/nearest_branches.php              (returns all, alpha order)
 *
 * Response:
 *   {
 *     "status": "success",
 *     "count": 28,
 *     "sorted_by": "distance",
 *     "data": [
 *       {
 *         "id": 1,
 *         "branch_code": "ALAJAWEED",
 *         "name": "Al Ajaweed",
 *         "address": "Jeddah",
 *         "phone": "0543448940",
 *         "maps_url": "https://maps.app.goo.gl/...",
 *         "latitude": 21.5433,
 *         "longitude": 39.1728,
 *         "distance_km": 2.34
 *       },
 *       ...
 *     ]
 *   }
 *
 * CORS: open to allow Shopify storefront JS to call this.
 * Authentication: none (public endpoint, read-only).
 */

// ---------- CORS & Response Headers ----------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=300'); // 5-minute browser cache

// Respond to CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------- Bootstrap ----------
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

// ---------- Input Validation ----------
$userLat  = isset($_GET['lat'])   ? (float) $_GET['lat']   : null;
$userLng  = isset($_GET['lng'])   ? (float) $_GET['lng']   : null;
$limit    = isset($_GET['limit']) ? (int)   $_GET['limit'] : 0;    // 0 = no limit
$search   = isset($_GET['q'])     ? trim($_GET['q'])        : '';   // text search

// Basic sanity check for coordinates
$hasCoords = ($userLat !== null && $userLng !== null)
          && ($userLat >= -90  && $userLat <= 90)
          && ($userLng >= -180 && $userLng <= 180);

try {
    $db = Database::getConnection();

    // ---------- Build Query ----------
    // Uses MySQL's built-in ST_Distance_Sphere (MySQL 5.7+, MariaDB 10.1+)
    // which accepts POINT geometries (lng, lat — note reversed order for GeoJSON).
    // Falls back to manual Haversine if user lat/lng not provided.

    if ($hasCoords) {
        /*
         * Haversine via SQL — works on MySQL 5.7 and MariaDB without spatial extensions.
         * Formula: d = 2R × asin(√(sin²(Δlat/2) + cos(lat1)·cos(lat2)·sin²(Δlng/2)))
         * R = 6371 km
         */
        $sql = "
            SELECT
                id,
                branch_code,
                branch_name                    AS name,
                address,
                phone,
                maps_url,
                latitude,
                longitude,
                ROUND(
                    6371 * 2 * ASIN(
                        SQRT(
                            POWER(SIN(RADIANS(latitude  - :ulat) / 2), 2) +
                            COS(RADIANS(:ulat2)) * COS(RADIANS(latitude)) *
                            POWER(SIN(RADIANS(longitude - :ulng) / 2), 2)
                        )
                    ),
                    2
                ) AS distance_km
            FROM  branches
            WHERE status   = 'active'
              AND deleted_at IS NULL
              AND latitude   IS NOT NULL
              AND longitude  IS NOT NULL
        ";

        // Optional text search (branch name or city/district)
        $searchParam = '';
        if ($search !== '') {
            $sql .= " AND (branch_name LIKE :search OR address LIKE :search2) ";
            $searchParam = '%' . $search . '%';
        }

        $sql .= " ORDER BY distance_km ASC ";

        if ($limit > 0) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':ulat',  $userLat, PDO::PARAM_STR);
        $stmt->bindValue(':ulat2', $userLat, PDO::PARAM_STR);
        $stmt->bindValue(':ulng',  $userLng, PDO::PARAM_STR);

        if ($search !== '') {
            $stmt->bindValue(':search',  $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        }

        $stmt->execute();
        $sortedBy = 'distance';

    } else {
        // No coordinates — return all branches alphabetically
        $sql = "
            SELECT
                id,
                branch_code,
                branch_name AS name,
                address,
                phone,
                maps_url,
                latitude,
                longitude,
                NULL AS distance_km
            FROM  branches
            WHERE status    = 'active'
              AND deleted_at IS NULL
        ";

        if ($search !== '') {
            $sql .= " AND (branch_name LIKE :search OR address LIKE :search2) ";
        }

        $sql .= " ORDER BY branch_name ASC ";

        if ($limit > 0) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = $db->prepare($sql);

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $stmt->bindValue(':search',  $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        }

        $stmt->execute();
        $sortedBy = 'name';
    }

    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast numeric fields
    foreach ($branches as &$b) {
        $b['id']          = (int)   $b['id'];
        $b['latitude']    = $b['latitude']    !== null ? (float) $b['latitude']    : null;
        $b['longitude']   = $b['longitude']   !== null ? (float) $b['longitude']   : null;
        $b['distance_km'] = $b['distance_km'] !== null ? (float) $b['distance_km'] : null;
    }
    unset($b);

    echo json_encode([
        'status'    => 'success',
        'count'     => count($branches),
        'sorted_by' => $sortedBy,
        'user_lat'  => $userLat,
        'user_lng'  => $userLng,
        'data'      => $branches,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

<?php
/**
 * GeoService.php
 * ---------------------------------------------------------
 * GPS geocoding and nearest-branch calculation.
 *
 * Geocoding: Uses Nominatim (OpenStreetMap) — completely free,
 * no API key required. Rate limit: 1 request/second (fine for
 * order-import use, which runs at most a few times per minute).
 *
 * Nearest branch: Pure SQL Haversine formula, no external calls.
 *
 * Usage:
 *   $coords = GeoService::geocodeShopifyAddress($addressObj);
 *   // $addressObj = ['address1'=>'...', 'city'=>'جدة', 'zip'=>'23433', 'country'=>'Saudi Arabia']
 *
 *   if ($coords) {
 *       $branchId = GeoService::findNearestBranch($db, $coords['lat'], $coords['lng']);
 *   }
 */
class GeoService
{
    // Nominatim public endpoint (OpenStreetMap)
    const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    // User-Agent is REQUIRED by Nominatim policy
    const USER_AGENT = 'Sheeralateen-CRM/1.0 (sheeralateen.fix4.in)';

    // Geocoding timeout — short so it never blocks order import
    const TIMEOUT_SECONDS = 5;

    /**
     * Geocode a Shopify shipping_address array to GPS coordinates.
     *
     * Tries progressively simpler queries until one succeeds:
     *   1. postal_code + city + country   (most precise)
     *   2. city + country                 (district-level fallback)
     *
     * @param  array $addressObj  Keys: address1, address2, city, province, zip, country
     * @return array{lat:float,lng:float}|null
     */
    public static function geocodeShopifyAddress(array $addressObj): ?array
    {
        $zip     = trim($addressObj['zip']     ?? '');
        $city    = trim($addressObj['city']    ?? '');
        $country = trim($addressObj['country'] ?? 'Saudi Arabia');

        // Build candidate queries from most to least specific
        $queries = [];
        if ($zip && $city) {
            $queries[] = "{$zip}, {$city}, {$country}";
        }
        if ($city) {
            $queries[] = "{$city}, {$country}";
        }

        foreach ($queries as $query) {
            $result = self::geocodeText($query, $country);
            if ($result) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Geocode a plain text address string.
     * Restricts results to Saudi Arabia by default.
     *
     * @return array{lat:float,lng:float}|null
     */
    public static function geocodeText(string $query, string $countryCode = 'sa'): ?array
    {
        if (empty(trim($query))) {
            return null;
        }

        // Nominatim uses ISO 3166-1 alpha-2 country codes
        $cc = strtolower($countryCode);
        if (strlen($cc) > 2) {
            // Convert full country name to code
            $countryMap = [
                'saudi arabia' => 'sa',
                'united arab emirates' => 'ae',
                'kuwait' => 'kw',
                'bahrain' => 'bh',
                'oman' => 'om',
                'qatar' => 'qa',
            ];
            $cc = $countryMap[strtolower($countryCode)] ?? 'sa';
        }

        $url = self::NOMINATIM_URL . '?' . http_build_query([
            'q'               => $query,
            'format'          => 'json',
            'limit'           => 1,
            'countrycodes'    => $cc,
            'accept-language' => 'en',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data[0]['lat'])) {
            return null;
        }

        return [
            'lat' => (float) $data[0]['lat'],
            'lng' => (float) $data[0]['lon'],
        ];
    }

    /**
     * Find the nearest active branch to a GPS coordinate.
     *
     * Uses the Haversine formula in SQL — no PHP loops, one query.
     * Requires branches table to have latitude and longitude columns
     * (see migration_add_coords.sql in sheeralateen-pickup-extension).
     *
     * @param  PDO   $db
     * @param  float $lat  Target latitude
     * @param  float $lng  Target longitude
     * @return int|null    Branch ID, or null if no branches have coordinates
     */
    public static function findNearestBranch(PDO $db, float $lat, float $lng): ?int
    {
        $stmt = $db->prepare("
            SELECT id,
                   ROUND(
                       6371 * ACOS(
                           COS(RADIANS(:lat))  * COS(RADIANS(latitude))
                           * COS(RADIANS(longitude) - RADIANS(:lng))
                           + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
                       ),
                   2) AS distance_km
            FROM   branches
            WHERE  status     = 'active'
              AND  deleted_at IS NULL
              AND  latitude   IS NOT NULL
              AND  longitude  IS NOT NULL
            ORDER  BY distance_km ASC
            LIMIT  1
        ");
        $stmt->execute([':lat' => $lat, ':lng' => $lng, ':lat2' => $lat]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }
}

<?php

class LeajlakService
{
    // The base URL for Leajlak's REST API
    const PROD_URL    = 'https://app.leajlak.com/api/partner-v2';
    const STAGING_URL = 'https://staging.4ulogistic.com/api/partner-v2';

    // Values from Leajlak REST API specs
    const PAYMENT_PREPAID         = '0'; // 0 = pre paid
    const PAYMENT_CASH            = '1'; // 1 = COD

    private PDO $db;
    private string $apiToken;
    private string $baseUrl;

    public function __construct(PDO $db)
    {
        $this->db       = $db;
        $this->apiToken = get_setting($db, 'leajlak_api_token', '');
        $env = get_setting($db, 'leajlak_environment', 'production');
        $this->baseUrl  = ($env === 'staging') ? self::STAGING_URL : self::PROD_URL;
    }

    /**
     * Helper to create an order from a DB row array
     */
    public function createOrderFromRow(array $order): array
    {
        $paymentMode = ($order['payment_status'] ?? 'pending') === 'paid'
            ? self::PAYMENT_PREPAID
            : self::PAYMENT_CASH;

        return $this->createOrder(
            $order['order_number'],
            $order['customer_phone']   ?? '',
            $order['delivery_address'] ?? '',
            (float) ($order['total_amount'] ?? 0),
            $paymentMode,
            $order['leajlak_shop_id'] ?? null,
            $order['customer_name'] ?? null
        );
    }

    /**
     * Create a delivery order via JSON REST API.
     */
    public function createOrder(
        string $clientOrderId,
        string $customerPhone,
        string $address,
        float  $amount,
        string $paymentMode = self::PAYMENT_CASH,
        ?string $shopId = null,
        ?string $customerName = null
    ): array {
        if (empty($this->apiToken)) {
            return $this->fail('leajlak_api_token not configured in system_settings');
        }

        // Format the address if it is JSON from Shopify
        $formattedAddress = $address;
        $decoded = json_decode($address, true);
        if (is_array($decoded)) {
            $parts = [];
            if (!empty($decoded['address1'])) $parts[] = $decoded['address1'];
            if (!empty($decoded['address2'])) $parts[] = $decoded['address2'];
            if (!empty($decoded['city'])) $parts[] = $decoded['city'];
            if (!empty($decoded['province'])) $parts[] = $decoded['province'];
            if (!empty($decoded['zip'])) $parts[] = $decoded['zip'];
            if (!empty($decoded['country'])) $parts[] = $decoded['country'];
            $formattedAddress = implode(', ', $parts);
        }

        // Parse coordinates from address (if it happened to be raw lat,lon)
        $lat = 21.5433;
        $lon = 39.1728;
        if (preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', trim($formattedAddress))) {
            $parts = explode(',', $formattedAddress);
            $lat = (float)trim($parts[0]);
            $lon = (float)trim($parts[1]);
        }

        $payload = [
            'id' => preg_replace('/[^A-Za-z0-9]/', '', $clientOrderId),
            'shop_id' => $shopId ?: '17081', // default fallback shop ID
            'delivery_details' => [
                'name' => $customerName ?: 'Customer',
                'phone' => $customerPhone,
                'coordinate' => [
                    'latitude' => $lat,
                    'longitude' => $lon
                ],
                'address' => $formattedAddress
            ],
            'order' => [
                'payment_type' => (int)$paymentMode,
                'total' => $amount,
                'notes' => "Order {$clientOrderId}"
            ]
        ];

        $ch = curl_init($this->baseUrl . '/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiToken
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 500) {
            return $this->fail("POST /orders returned HTTP {$httpCode}");
        }
        if ($httpCode >= 400) {
            // Attempt to extract JSON error message
            $errorMsg = "API returned HTTP {$httpCode}";
            $jsonResp = json_decode($body, true);
            if ($jsonResp && isset($jsonResp['message'])) {
                $errorMsg = $jsonResp['message'];
            }
            return $this->fail("Leajlak Error: " . $errorMsg);
        }

        $jsonResp = json_decode($body, true);
        
        // Extract order ID from response if available (assuming 'order_id' or 'id')
        $leajlakOrderId = $jsonResp['dsp_order_id'] ?? $jsonResp['order_id'] ?? $jsonResp['id'] ?? null;

        return [
            'success'          => true,
            'leajlak_order_id' => $leajlakOrderId,
            'error'            => null,
        ];
    }

    
    /**
     * Cancel an order in Leajlak via REST API.
     * Leajlak allows cancellation before pickup (status < Order Picked).
     *
     * @param string $dspOrderId The dsp_order_id (UUID string) of the order in Leajlak.
     * @param string $reason Optional reason for cancellation.
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function cancelOrder(string $dspOrderId, string $reason = 'Cancelled by store manager'): array
    {
        if (empty($this->apiToken)) {
            return $this->fail('leajlak_api_token not configured in system_settings');
        }
        if (empty($dspOrderId)) {
            return $this->fail('No Leajlak order ID (dsp_order_id) found for this order.');
        }

        $url = $this->baseUrl . '/orders/' . urlencode(trim($dspOrderId));
        $payload = [
            'cancellation_reason' => $reason ?: 'Cancelled by store manager'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiToken
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Expected 202 Accepted (or 200)
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'error'   => null,
            ];
        }

        $jsonResp = json_decode($body, true);
        $errorMsg = $jsonResp['message'] ?? "API returned HTTP {$httpCode}";
        return $this->fail("Leajlak Cancel Error: " . $errorMsg);
    }

    /**
     * Create a shop (branch) under the client account via JSON REST API.
     */
    public function createShop(
        string $shopName,
        string $address,
        string $contactPersonName,
        string $contactPersonPhone,
        string $shopEmail,
        string $location = ''
    ): array {
        if (empty($this->apiToken)) {
            return $this->fail('leajlak_api_token not configured in system_settings');
        }

        $payload = [
            'name' => $shopName,
            'location' => $location,
            'address' => $address,
            'contact_person_name' => $contactPersonName,
            'contact_person_phone' => $contactPersonPhone,
            'shop_email' => $shopEmail
        ];

        if (empty($location)) {
            $payload['location'] = '21.5433,39.1728';
        }

        $ch = curl_init('https://app.leajlak.com/api/partner/shops');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiToken
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 500) {
            return $this->fail("POST /shops returned HTTP {$httpCode} - $body");
        }
        if ($httpCode >= 400) {
            $errorMsg = "API returned HTTP {$httpCode}";
            $jsonResp = json_decode($body, true);
            if ($jsonResp && isset($jsonResp['message'])) {
                $errorMsg = $jsonResp['message'];
            }
            return $this->fail("Leajlak Error: " . $errorMsg . " - $body");
        }

        $jsonResp = json_decode($body, true);
        $shopId = $jsonResp['shop_id'] ?? null;

        return [
            'success' => true,
            'shop_id' => $shopId,
            'response' => $jsonResp,
            'error'   => null,
        ];
    }

    private function fail(string $error): array
    {
        return ['success' => false, 'leajlak_order_id' => null, 'error' => $error];
    }
}



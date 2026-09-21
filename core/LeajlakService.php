<?php

class LeajlakService
{
    // The base URL for Leajlak's REST API
    const BASE_URL = 'https://staging.4ulogistic.com/api/partner-v2';

    // Values from Leajlak REST API specs
    const PAYMENT_PREPAID         = '0'; // 0 = pre paid
    const PAYMENT_CASH            = '1'; // 1 = COD

    private PDO $db;
    private string $apiToken;

    public function __construct(PDO $db)
    {
        $this->db       = $db;
        $this->apiToken = get_setting($db, 'leajlak_api_token', '');
        
        // Fallback to password field if they haven't migrated their token yet
        if (empty($this->apiToken)) {
            $this->apiToken = get_setting($db, 'leajlak_password', '');
        }
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

        // Parse coordinates from address
        $lat = 21.5433;
        $lon = 39.1728;
        if (preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', trim($address))) {
            $parts = explode(',', $address);
            $lat = (float)trim($parts[0]);
            $lon = (float)trim($parts[1]);
        }

        $payload = [
            'id' => preg_replace('/[^A-Za-z0-9]/', '', $clientOrderId),
            'shop_id' => $shopId ?: '821017856', // default fallback shop ID
            'delivery_details' => [
                'name' => $customerName ?: 'Customer',
                'phone' => $customerPhone,
                'coordinate' => [
                    'latitude' => $lat,
                    'longitude' => $lon
                ],
                'address' => $address
            ],
            'order' => [
                'payment_type' => (int)$paymentMode,
                'total' => $amount,
                'notes' => "Order {$clientOrderId}"
            ]
        ];

        $ch = curl_init(self::BASE_URL . '/orders');
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
        $leajlakOrderId = $jsonResp['order_id'] ?? $jsonResp['id'] ?? null;

        return [
            'success'          => true,
            'leajlak_order_id' => $leajlakOrderId,
            'error'            => null,
        ];
    }

    private function fail(string $error): array
    {
        return ['success' => false, 'leajlak_order_id' => null, 'error' => $error];
    }
}

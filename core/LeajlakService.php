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
        $this->apiToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxMCIsImp0aSI6IjRhZGVlNWIwNDg5NzIwZDFkNTEyZTA5Zjk4NTk5OTUwZmFlNDFjZjkyZDgxNDhhMzE2NzNmYmE4MjQyYjkzYzY4ODI3NDU2M2EzZWQ3YmJlIiwiaWF0IjoxNzkwMDAyNzIxLjM5NTM4NSwibmJmIjoxNzkwMDAyNzIxLjM5NTM4NywiZXhwIjo0OTQ1Njc2MzIxLjM5MDA1Niwic3ViIjoiNTMwIiwic2NvcGVzIjpbXX0.rMZ_jwkHhFe6yml_l-ufiEhRGYo5g5WyTkbdk3LLcwRPHymNMOQ6_HRVPW1VPG6NfXqltPduUDXF8iE0twGAn-Hyv0GxsRzjSpDYY2k4I9J1XjpcC-hMonkJrMy7V9cPjORi-vusl0A6QS5nCf58bx4wXMN-8QAZQZ0U3wXzxLntxHnkBIl79e0moCFv0lDb7xftGeSBVhrgDGLd05238eM_58FNBazMd9EpaO4T1p8uHwFVH8EBOSLJMmbxWjE8j6ScNJgtPK7alianNQdrewVvnEvO9RKrL3hUZcCI7OzZCqP6hf0-YgbWwuNsz3aU_KSCM6OAfMd04GRLjmNCwc5zcmSkZ8qDjtpWRxbqBS89dwTWpyYRKrTveJ2D09LhRvFpls3m4HPq1fGhzZwN_uHqz7JBVY2tKHj_cOMtbK5WtQVxLXmpARo1KsCPCqIPkpE4SNmQtM8ABt1wlLKUdeMHl5g-Hicirlkqec5bWcB_2BdHgnN5IPp-BMx_ZssbR92xmRvRlOXoSPcLEW3dZKrxzH9XLi34EV5BfvR6nMPgcuVAz9V14WmI7pZuzfUYkYhok4BgcLLdZkWJr96-6byVIAB4ABwrJwU31Mn_3n7Aq4WYwDtODTVy4khwlbM6MqGV3CtNeJdRKWuS_ZCa0d9KZuNsm5RnDYFAKr-sC7w';
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
            'shop_id' => $shopId ?: '821017856', // default fallback shop ID
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

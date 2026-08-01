<?php
/**
 * core/ShopifyService.php
 * ---------------------------------------------------------
 * Low-level Shopify Admin API client.
 * Handles authentication, rate limiting, and raw HTTP requests.
 * All logic must stay inside this reusable service.
 */
class ShopifyService
{
    private PDO $db;
    private string $storeUrl;
    private string $apiVersion;
    private string $apiToken;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->storeUrl = rtrim(get_setting($db, 'shopify_store_url'), '/');
        $this->apiVersion = get_setting($db, 'shopify_api_version', '2026-04');
        
        $encryptedToken = get_setting($db, 'shopify_api_token');
        $this->apiToken = $this->decryptToken($encryptedToken);
    }

    /**
     * Helper to decrypt the token stored in the database.
     */
    private function decryptToken(string $encrypted): string
    {
        if (empty($encrypted)) return '';
        if (!defined('APP_SECRET') || empty(APP_SECRET)) {
            // Fallback if APP_SECRET isn't configured yet
            return $encrypted; 
        }
        
        $parts = explode('::', base64_decode($encrypted));
        if (count($parts) !== 2) return $encrypted; // Probably not encrypted yet
        
        list($encrypted_data, $iv) = $parts;
        $key = hash('sha256', APP_SECRET);
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, base64_decode($iv));
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Checks if Shopify credentials exist.
     */
    public function isConfigured(): bool
    {
        return !empty($this->storeUrl) && !empty($this->apiToken);
    }

    /**
     * Test connection to the Shopify API.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Shopify settings are incomplete.'];
        }

        try {
            // Try fetching shop info
            $response = $this->request('GET', '/shop.json');
            if (isset($response['shop'])) {
                return ['ok' => true, 'message' => 'Connected successfully to ' . $response['shop']['name']];
            }
            return ['ok' => false, 'message' => 'Invalid response from Shopify.'];
        } catch (Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * GET /orders.json
     */
    public function getOrders(array $params = []): array
    {
        $query = http_build_query($params);
        $endpoint = '/orders.json' . ($query ? '?' . $query : '');
        return $this->request('GET', $endpoint);
    }

    /**
     * GET /orders/{id}.json
     */
    public function getOrder(string $orderId): array
    {
        return $this->request('GET', "/orders/{$orderId}.json");
    }

    /**
     * GET /orders/{id}/fulfillment_orders.json
     */
    public function getOrderFulfillmentOrders(string $orderId): array
    {
        return $this->request('GET', "/orders/{$orderId}/fulfillment_orders.json");
    }

    /**
     * GET /locations.json
     */
    public function getLocations(): array
    {
        return $this->request('GET', '/locations.json');
    }

    /**
     * PUT /orders/{id}.json to update tags
     */
    public function updateOrderTags(string $orderId, string $tags): array
    {
        $payload = [
            'order' => [
                'id' => $orderId,
                'tags' => $tags
            ]
        ];
        return $this->request('PUT', "/orders/{$orderId}.json", $payload);
    }

    /**
     * POST /fulfillments.json
     * Marks an entire order as fulfilled.
     */
    public function createFulfillment(string $orderId, ?string $trackingNumber = null, ?string $trackingCompany = null): array
    {
        // First, get the fulfillment order ID (Required in newer API versions)
        // Shopify migrated from Order fulfillments to FulfillmentOrders
        $foResponse = $this->request('GET', "/orders/{$orderId}/fulfillment_orders.json");
        $fulfillmentOrders = $foResponse['fulfillment_orders'] ?? [];
        
        if (empty($fulfillmentOrders)) {
            throw new Exception("No unfulfilled lines found for this order.");
        }

        // Just fulfill the first open fulfillment order for simplicity in Phase 4
        $fulfillmentOrderId = null;
        foreach ($fulfillmentOrders as $fo) {
            if (in_array($fo['status'], ['open', 'in_progress'])) {
                $fulfillmentOrderId = $fo['id'];
                break;
            }
        }

        if (!$fulfillmentOrderId) {
            throw new Exception("Order is already fulfilled or cannot be fulfilled.");
        }

        $payload = [
            'fulfillment' => [
                'message' => 'Fulfilled via Branch Management System',
                'notify_customer' => true,
                'line_items_by_fulfillment_order' => [
                    [
                        'fulfillment_order_id' => $fulfillmentOrderId
                    ]
                ]
            ]
        ];

        if ($trackingNumber) {
            $payload['fulfillment']['tracking_info'] = [
                'number' => $trackingNumber,
                'company' => $trackingCompany ?? 'Other'
            ];
        }

        return $this->request('POST', '/fulfillments.json', $payload);
    }

    /**
     * POST /orders/{id}/cancel.json
     * Cancels an order.
     */
    public function cancelOrder(string $orderId, bool $emailCustomer = true): array
    {
        $payload = [
            'email' => $emailCustomer
        ];
        return $this->request('POST', "/orders/{$orderId}/cancel.json", $payload);
    }

    /**
     * POST /orders/{id}/transactions.json
     * Creates a transaction (e.g. to mark as paid)
     */
    public function createTransaction(string $orderId, string $kind = 'sale'): array
    {
        // To properly mark as paid, we need the order's outstanding balance
        $order = $this->getOrder($orderId);
        $orderData = $order['order'] ?? null;
        if (!$orderData) {
            throw new Exception("Order not found for transaction.");
        }
        
        $payload = [
            'transaction' => [
                'currency' => $orderData['currency'],
                'amount' => $orderData['current_total_price'] ?? $orderData['total_price'],
                'kind' => $kind,
                'gateway' => 'manual'
            ]
        ];
        
        return $this->request('POST', "/orders/{$orderId}/transactions.json", $payload);
    }

    /**
     * Core cURL request method
     */
    private function request(string $method, string $endpoint, ?array $payload = null): array
    {
        if (!$this->isConfigured()) {
            throw new Exception("Shopify API not configured.");
        }

        $url = $this->storeUrl . '/admin/api/' . $this->apiVersion . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $this->apiToken
        ];

        if ($payload !== null) {
            $jsonPayload = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errMsg = "HTTP $httpCode";
            if (isset($decoded['errors'])) {
                $errMsg .= " - " . (is_array($decoded['errors']) ? json_encode($decoded['errors']) : $decoded['errors']);
            } elseif (isset($decoded['error'])) {
                $errMsg .= " - " . $decoded['error'];
            }
            throw new Exception("Shopify API Error: " . $errMsg);
        }

        return $decoded ?: [];
    }
}

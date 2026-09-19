<?php
/**
 * LeajlakService.php
 * ---------------------------------------------------------
 * Handles outbound delivery order creation via the Leajlak
 * web portal (staging.4ulogistic.com).
 *
 * Leajlak does not expose a REST API that accepts Bearer
 * token authentication for order creation. This service
 * automates the authenticated web form at
 * /client/orders/create using PHP cURL with session cookie
 * handling — exactly replicating what the browser does.
 *
 * ── Web session flow ────────────────────────────────────────
 *   1. GET  /login                  → extract CSRF token
 *   2. POST /login                  → authenticate, receive session cookie
 *   3. GET  /client/orders/create   → extract fresh CSRF token
 *   4. POST /client/orders/create   → submit order form
 *
 * ── Required system_settings keys ──────────────────────────
 *   leajlak_email    = login email for the Leajlak dispatcher account
 *   leajlak_password = login password
 *
 * ── Static identifiers (Sheeralateen account) ───────────────
 *   client_id = 181       (Sheeralateen's Leajlak client ID)
 *   shop      = 821017856 (Sheeralateen's Leajlak shop ID)
 *
 * ── Trigger ─────────────────────────────────────────────────
 *   Called when an order current_status transitions to
 *   'Ready' — the branch has packed the order and it is
 *   ready for driver pickup.
 */

class LeajlakService
{
    const BASE_URL  = 'https://staging.4ulogistic.com';
    const CLIENT_ID = '181';
    const SHOP_ID   = '821017856';

    // Values from Leajlak delivery_payment_mode <select>
    const PAYMENT_PREPAID         = 'Pre Paid';
    const PAYMENT_CASH            = 'Auto';            // shown as "Cash" in UI
    const PAYMENT_SWIPING_MACHINE = 'Swiping Machine';

    private PDO    $db;
    private string $email;
    private string $password;

    public function __construct(PDO $db)
    {
        $this->db       = $db;
        $this->email    = get_setting($db, 'leajlak_email',    '');
        $this->password = get_setting($db, 'leajlak_password', '');
    }

    // ─────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────

    /**
     * Create a delivery order in Leajlak.
     *
     * @param  string $clientOrderId  Your system order number (e.g. "1089")
     * @param  string $customerPhone  Customer mobile digits (e.g. "0501234567")
     * @param  string $address        Delivery address text
     * @param  float  $amount         Bill amount (SAR)
     * @param  string $paymentMode    One of the PAYMENT_* constants (default: Cash)
     * @return array{success:bool, leajlak_order_id:string|null, error:string|null}
     */
    public function createOrder(
        string $clientOrderId,
        string $customerPhone,
        string $address,
        float  $amount,
        string $paymentMode = self::PAYMENT_CASH
    ): array {
        if (empty($this->email) || empty($this->password)) {
            return $this->fail(
                'leajlak_email or leajlak_password not configured in system_settings'
            );
        }

        // Each order creation uses its own temp cookie jar so concurrent
        // requests do not overwrite each other's sessions.
        $cookieFile = tempnam(sys_get_temp_dir(), 'leajlak_');

        try {
            // ── Step 1: Login ────────────────────────────────
            $loginResult = $this->login($cookieFile);
            if (!$loginResult['success']) {
                return $this->fail('Leajlak login failed: ' . $loginResult['error']);
            }

            // ── Step 2: Fetch CSRF token ──────────────────────
            $tokenResult = $this->fetchCsrfToken($cookieFile);
            if (!$tokenResult['success']) {
                return $this->fail('CSRF fetch failed: ' . $tokenResult['error']);
            }

            // ── Step 3: Submit order ──────────────────────────
            return $this->submitOrder(
                $cookieFile,
                $tokenResult['token'],
                $clientOrderId,
                $customerPhone,
                $address,
                $amount,
                $paymentMode
            );
        } finally {
            if (file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    /**
     * Convenience wrapper: build the call from a local orders table row.
     * Maps payment_status (paid → Pre Paid, otherwise → Cash).
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
            $paymentMode
        );
    }

    // ─────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────

    private function login(string $cookieFile): array
    {
        // 1a. GET login page → extract CSRF token
        $html = $this->get('/login', $cookieFile);
        if ($html === null) {
            return ['success' => false, 'error' => 'GET /login failed'];
        }

        $token = $this->extractCsrf($html);
        if (!$token) {
            return ['success' => false, 'error' => 'No CSRF token on login page'];
        }

        // 1b. POST credentials
        $ch = $this->makeCurl(self::BASE_URL . '/login', $cookieFile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            '_token'   => $token,
            'email'    => $this->email,
            'password' => $this->password,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: ' . self::BASE_URL . '/login',
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 500) {
            return ['success' => false, 'error' => "POST /login returned HTTP {$httpCode}"];
        }
        if ($body && str_contains($body, 'These credentials do not match')) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }

        return ['success' => true, 'error' => null];
    }

    private function fetchCsrfToken(string $cookieFile): array
    {
        $html = $this->get('/client/orders/create', $cookieFile);
        if ($html === null) {
            return ['success' => false, 'error' => 'GET /client/orders/create failed'];
        }

        // If we landed back on the login page, session was not established
        if (str_contains($html, 'name="email"')) {
            return ['success' => false, 'error' => 'Redirected to login — credentials may be wrong'];
        }

        $token = $this->extractCsrf($html);
        if (!$token) {
            return ['success' => false, 'error' => 'No CSRF token on create-order page'];
        }

        return ['success' => true, 'token' => $token, 'error' => null];
    }

    private function submitOrder(
        string $cookieFile,
        string $csrfToken,
        string $clientOrderId,
        string $customerPhone,
        string $address,
        float  $amount,
        string $paymentMode
    ): array {
        // [] in field names is how Laravel receives array inputs.
        $fields = http_build_query([
            '_token'                  => $csrfToken,
            'client_id'               => self::CLIENT_ID,
            'shop'                    => self::SHOP_ID,
            'client_order_id[]'       => $clientOrderId,
            'delivery_type[]'         => '1',  // only option: "Express OR Fast"
            'delivery_date[]'         => '',   // empty = immediate dispatch
            'delivery_time[]'         => '',   // no time slots available
            'customer_number[]'       => $customerPhone,
            'delivery_payment_mode[]' => $paymentMode,
            'amount[]'                => number_format($amount, 2, '.', ''),
            'address[]'               => $address,
        ]);

        $ch = $this->makeCurl(self::BASE_URL . '/client/orders/create', $cookieFile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: ' . self::BASE_URL . '/client/orders/create',
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 500) {
            return $this->fail("POST /client/orders/create returned HTTP {$httpCode}");
        }
        if ($body && str_contains($body, 'has already been taken')) {
            return $this->fail("Order '{$clientOrderId}' is already registered in Leajlak");
        }
        if ($httpCode >= 400) {
            return $this->fail("Order submission returned HTTP {$httpCode}");
        }

        // Try to extract the Leajlak order reference (e.g. "OIm#505") from
        // the response page after the redirect lands on the order list.
        $leajlakOrderId = null;
        if ($body && preg_match('/\bOI[a-zA-Z]#(\d+)\b/i', $body, $m)) {
            $leajlakOrderId = $m[0];
        }

        return [
            'success'          => true,
            'leajlak_order_id' => $leajlakOrderId,
            'error'            => null,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // cURL utilities
    // ─────────────────────────────────────────────────────────

    private function get(string $path, string $cookieFile): ?string
    {
        $ch   = $this->makeCurl(self::BASE_URL . $path, $cookieFile);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code < 400) ? $body : null;
    }

    private function makeCurl(string $url, string $cookieFile): \CurlHandle
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
                                    . ' AppleWebKit/537.36 (KHTML, like Gecko)'
                                    . ' Chrome/127.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Referer: ' . self::BASE_URL,
            ],
        ]);
        return $ch;
    }

    private function extractCsrf(string $html): ?string
    {
        if (preg_match(
            '/<input[^>]+name=["\']_token["\'][^>]+value=["\']([^"\']+)["\']/i',
            $html, $m
        )) {
            return $m[1];
        }
        if (preg_match(
            '/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/i',
            $html, $m
        )) {
            return $m[1];
        }
        return null;
    }

    private function fail(string $error): array
    {
        return ['success' => false, 'leajlak_order_id' => null, 'error' => $error];
    }
}

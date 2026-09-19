<?php
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/LeajlakService.php';

class DebugLeajlak extends LeajlakService {
    public function debugOrder() {
        $cookieFile = tempnam(sys_get_temp_dir(), 'leajlak_');
        $this->login($cookieFile);
        $token = $this->fetchCsrfToken($cookieFile)['token'];
        
        $fields = http_build_query([
            '_token'                  => $token,
            'client_id'               => '181',
            'shop'                    => '821017856',
            'client_order_id[]'       => 'DBG-' . date('His'),
            'delivery_type[]'         => '1',
            'delivery_date[]'         => '',
            'delivery_time[]'         => '',
            'customer_number[]'       => '0501234567',
            'delivery_payment_mode[]' => self::PAYMENT_CASH,
            'amount[]'                => '50.00',
            'address[]'               => 'Test Address',
        ]);

        $ch = curl_init(self::BASE_URL . '/client/orders/create');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Referer: ' . self::BASE_URL . '/client/orders/create',
            ],
        ]);
        
        $body = curl_exec($ch);
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        
        echo "Final URL: $url\n\n";
        echo "Body HTML:\n";
        
        // Extract Laravel validation errors if any
        if (preg_match_all('/<strong[^>]*>(.*?)<\/strong>/is', $body, $m)) {
            print_r($m[1]);
        }
        if (preg_match_all('/class="invalid-feedback"[^>]*>(.*?)<\/div>/is', $body, $m)) {
            print_r($m[1]);
        }
        if (preg_match_all('/<span class="text-danger">(.*?)<\/span>/is', $body, $m)) {
            print_r($m[1]);
        }
        
        @unlink($cookieFile);
    }
}

$db = Database::getConnection();
$debug = new DebugLeajlak($db);
$debug->debugOrder();

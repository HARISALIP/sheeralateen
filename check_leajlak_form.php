<?php
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/LeajlakService.php';

// Login and fetch the order creation page HTML
$cookieFile = tempnam(sys_get_temp_dir(), 'leajlak_chk_');

// Step 1: GET login page for CSRF
$ch = curl_init('https://staging.4ulogistic.com/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR  => $cookieFile,
    CURLOPT_USERAGENT  => 'Mozilla/5.0',
]);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/<input[^>]+name=["\']_token["\'][^>]+value=["\']([^"\']+)["\']/i', $html, $m);
$token = $m[1] ?? '';

// Step 2: POST login
$ch = curl_init('https://staging.4ulogistic.com/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR  => $cookieFile,
    CURLOPT_USERAGENT  => 'Mozilla/5.0',
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'   => $token,
        'email'    => get_setting(Database::getConnection(), 'leajlak_email', ''),
        'password' => get_setting(Database::getConnection(), 'leajlak_password', ''),
    ]),
]);
curl_exec($ch);
curl_close($ch);

// Step 3: GET the order creation form
$ch = curl_init('https://staging.4ulogistic.com/client/orders/create');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR  => $cookieFile,
    CURLOPT_USERAGENT  => 'Mozilla/5.0',
]);
$formHtml = curl_exec($ch);
curl_close($ch);

@unlink($cookieFile);

// Extract all input/select/textarea names and their nearby labels
preg_match_all('/<(?:input|select|textarea)[^>]*name=["\']([^"\']+)["\']/i', $formHtml, $fields);
$names = array_unique($fields[1]);

header('Content-Type: text/plain');
echo "=== FORM FIELDS FOUND ===\n";
foreach ($names as $name) {
    echo "  - $name\n";
}

// Also look for any label text containing keywords
$keywords = ['pickup', 'collect', 'sender', 'from', 'origin', 'branch', 'store', 'warehouse'];
echo "\n=== LABELS CONTAINING PICKUP/COLLECT/SENDER KEYWORDS ===\n";
preg_match_all('/<label[^>]*>(.*?)<\/label>/is', $formHtml, $labels);
foreach ($labels[1] as $label) {
    $text = strtolower(strip_tags($label));
    foreach ($keywords as $kw) {
        if (str_contains($text, $kw)) {
            echo "  Found: " . trim(strip_tags($label)) . "\n";
            break;
        }
    }
}

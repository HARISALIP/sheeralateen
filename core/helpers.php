<?php
/**
 * helpers.php
 * ---------------------------------------------------------
 * Shared utility functions available across every page once
 * bootstrap.php is included.
 *
 * Functions:
 *   flash()        — Store a one-time flash message in the session.
 *   get_flash()    — Read + clear a flash message from the session.
 *   e()            — Safe HTML output (htmlspecialchars shorthand).
 *   get_setting()  — Read one key from system_settings.
 *   save_setting() — Upsert one key into system_settings.
 */

/**
 * Store a flash message for the next page load.
 */
function flash(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

/**
 * Read and clear a flash message. Returns null if not set.
 */
function get_flash(string $key): ?string
{
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

/**
 * Safe HTML-encoded output helper.
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Read one value from system_settings by key.
 * Returns $default if the key does not exist.
 */
function get_setting(PDO $db, string $key, string $default = ''): string
{
    $stmt = $db->prepare(
        "SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1"
    );
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetchColumn();
    return ($result !== false && $result !== null) ? (string) $result : $default;
}

/**
 * Insert or update one key in system_settings.
 */
function save_setting(PDO $db, string $key, ?string $value): void
{
    $stmt = $db->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (:key, :val)
        ON DUPLICATE KEY UPDATE setting_value = :val2
    ");
    $stmt->execute([':key' => $key, ':val' => $value, ':val2' => $value]);
}

/**
 * Encrypt a token for storage in the database using the APP_SECRET
 */
function encrypt_shopify_token(string $plainText): string
{
    if (empty($plainText)) return '';
    if (!defined('APP_SECRET') || empty(APP_SECRET)) {
        return $plainText; // Fallback, but shouldn't happen in production
    }
    
    $key = hash('sha256', APP_SECRET);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($plainText, 'aes-256-cbc', $key, 0, $iv);
    
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

<?php
/**
 * bootstrap.php
 * ---------------------------------------------------------
 * Include this one file at the top of every /admin and /branch
 * page. It loads config, starts a properly configured session,
 * and pulls in the shared classes.
 */

require_once __DIR__ . '/../config/config.php';

// Configure the session cookie before starting the session
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name(SESSION_NAME);
session_start();

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ActivityLogger.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

require_once __DIR__ . '/ShopifyService.php';
require_once __DIR__ . '/SyncQueue.php';
require_once __DIR__ . '/ShopifySyncService.php';

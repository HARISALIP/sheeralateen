<?php
/**
 * Application Configuration
 * ---------------------------------------------------------
 * Update DB_* constants with the values from Hostinger hPanel
 * (hPanel -> Databases -> MySQL Databases).
 *
 * This file lives outside the web-visible document flow in
 * spirit (protected by .htaccess), but is still included via
 * PHP require, not accessed directly by the browser.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'u265225504_sheera');
define('DB_USER', 'u265225504_sheera_app');
define('DB_PASS', 'dW5;dxH:');
define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
define('APP_NAME', 'Branch Management System');
define('APP_URL', 'https://sheeralateen.fix4.in'); // no trailing slash

// ---- Session ----
define('SESSION_NAME', 'bms_session');

// ---- Security (New in Phase 4) ----
// APP_SECRET is used to encrypt the Shopify API token in the database.
define('APP_SECRET', 'h7*K9$pL2!qW5@vX8^mN4#cR1%tY6&bZ');
// CRON_SECRET is required to trigger cron jobs from the web
define('CRON_SECRET', 'x9$Q2!mP7#vL5@wK4^jN8*bR1%tY6&cZ');

// ---- Timezone (adjust to your store's local timezone) ----
date_default_timezone_set('Asia/Dubai');

// ---- Error handling ----
// Keep display_errors OFF in production. Flip to '1' only while debugging locally.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

<?php
/**
 * Database
 * ---------------------------------------------------------
 * Thin singleton wrapper around PDO. Every part of the app
 * (admin, branch, and later Shopify sync jobs) pulls its
 * connection from here so there's exactly one place that
 * knows how to connect to MySQL.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                die('A system error occurred: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }

        return self::$instance;
    }
}

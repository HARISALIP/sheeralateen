<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');

try {
    $db->exec("ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
    echo "Successfully added 'shipping_fee' column to 'orders' table!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'shipping_fee' already exists!\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Describe orders table to verify
$stmt = $db->query("SHOW COLUMNS FROM orders LIKE 'shipping_fee'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));







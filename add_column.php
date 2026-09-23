<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

try {
    $db->exec("ALTER TABLE orders ADD COLUMN leajlak_status VARCHAR(50) NULL AFTER current_status");
    echo "Successfully added 'leajlak_status' column to 'orders' table!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'leajlak_status' already exists! You're good to go.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}

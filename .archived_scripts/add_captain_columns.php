<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

try {
    $db->exec("ALTER TABLE orders ADD COLUMN leajlak_captain_name VARCHAR(255) NULL AFTER leajlak_status");
    $db->exec("ALTER TABLE orders ADD COLUMN leajlak_captain_phone VARCHAR(50) NULL AFTER leajlak_captain_name");
    echo "Successfully added captain columns to 'orders' table!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist! You're good to go.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}

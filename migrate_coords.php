<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
try {
    $db->exec("ALTER TABLE branches ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER address");
    $db->exec("ALTER TABLE branches ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude");
    echo "Successfully added latitude and longitude columns to branches table!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Columns already exist!";
    } else {
        echo "Error: " . $e->getMessage();
    }
}


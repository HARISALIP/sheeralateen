<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== FOREIGN KEYS REFERENCING ORDERS ===\n";
$stmt = $db->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_NAME = 'orders' AND TABLE_SCHEMA = DATABASE()
");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

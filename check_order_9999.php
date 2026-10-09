<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("SELECT id, order_number FROM orders WHERE order_number LIKE \"%9999%\"");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));


<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->prepare("UPDATE orders SET deleted_at = NOW() WHERE order_number = :onum");
$stmt->execute([":onum" => "9999"]);
echo "Deleted " . $stmt->rowCount() . " orders.";


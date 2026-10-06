<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 15");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);


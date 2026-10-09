<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$stmt = $db->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 10");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($logs as $log) {
    echo $log["id"] . " | " . $log["action"] . " | " . $log["description"] . "<br>\n";
}


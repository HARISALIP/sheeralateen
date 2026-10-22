<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
header('Content-Type: text/plain');

$stmt = $db->query("SELECT NOW() as db_now, UTC_TIMESTAMP() as db_utc, @@global.time_zone, @@session.time_zone");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

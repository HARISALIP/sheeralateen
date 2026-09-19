<?php
require_once __DIR__ . '/core/bootstrap.php';
$stmt = Database::getConnection()->query("SELECT action, description, created_at FROM activity_logs ORDER BY id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

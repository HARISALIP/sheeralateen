<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT u.id, u.name, u.email, u.role, u.branch_id, b.branch_name, b.branch_code FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.deleted_at IS NULL ORDER BY u.role, u.id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>"; print_r($rows); echo "</pre>";

<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();
$search = "jumoom";
$where = "b.deleted_at IS NULL AND (b.branch_name LIKE :q OR b.branch_code LIKE :q OR b.email LIKE :q)";
$params = [":q" => "%" . $search . "%"];
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM branches b WHERE {$where}");
    $countStmt->execute($params);
    echo "Count: " . $countStmt->fetchColumn() . "\n";
    
    $listStmt = $db->prepare("
        SELECT b.*, u.name AS manager_name
        FROM   branches b
        LEFT JOIN users u ON b.branch_manager_id = u.id AND u.deleted_at IS NULL
        WHERE  {$where}
        ORDER  BY b.created_at DESC
        LIMIT  15 OFFSET 0
    ");
    $listStmt->execute($params);
    echo "Rows: " . count($listStmt->fetchAll()) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}


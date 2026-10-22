<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$stmt = $db->query("
    SELECT 
        b.id AS branch_id,
        b.branch_name,
        b.branch_code,
        b.status AS branch_status,
        b.phone,
        b.email AS branch_email,
        u.id AS user_id,
        u.name AS manager_name,
        u.email AS login_email,
        u.status AS user_status
    FROM branches b
    LEFT JOIN users u ON (u.branch_id = b.id OR b.branch_manager_id = u.id) AND u.deleted_at IS NULL
    WHERE b.deleted_at IS NULL
    ORDER BY b.id ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: text/plain; charset=utf-8');
echo "Branch ID | Branch Name | Branch Code | Branch Status | User ID | Manager Name | Login Email\n";
echo "----------------------------------------------------------------------------------------------------\n";
foreach ($rows as $r) {
    echo sprintf(
        "%-9s | %-20s | %-14s | %-13s | %-7s | %-22s | %s\n",
        $r['branch_id'],
        $r['branch_name'],
        $r['branch_code'],
        $r['branch_status'],
        $r['user_id'] ?? 'NONE',
        $r['manager_name'] ?? 'NONE',
        $r['login_email'] ?? 'NONE'
    );
}
exit;

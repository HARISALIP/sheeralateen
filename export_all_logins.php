<?php
/**
 * export_all_logins.php
 * ---------------------------------------------------------------------------
 * Downloads all branch login IDs and passwords as an Excel-compatible CSV file.
 */
require_once __DIR__ . '/core/bootstrap.php';
Auth::requireRole('super_admin', 'login.php');
$db = Database::getConnection();

// Query all branches with managers
$stmt = $db->query("
    SELECT 
        b.id AS branch_id,
        b.branch_name,
        b.branch_code,
        b.status AS branch_status,
        b.phone,
        u.id AS user_id,
        u.name AS manager_name,
        u.email AS login_email,
        u.role
    FROM branches b
    LEFT JOIN users u ON (u.branch_id = b.id OR b.branch_manager_id = u.id) AND u.deleted_at IS NULL
    WHERE b.deleted_at IS NULL AND b.branch_code != 'TEST01'
    ORDER BY b.id ASC
");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also fetch Super Admin
$adminStmt = $db->query("SELECT id, name, email, role, status FROM users WHERE role = 'super_admin' AND deleted_at IS NULL");
$superAdmin = $adminStmt->fetch(PDO::FETCH_ASSOC);

function resolvePassword($email) {
    if ($email === 'admin@sheeralateen.com') return 'sheeraadmin123';
    if ($email === 'sheera_jumoom@sheeralateen.com') return 'jumoom123';
    
    // Extract prefix before @
    $parts = explode('@', $email);
    $prefix = $parts[0] ?? '';
    return $prefix . '@321';
}

$filename = 'SheeraLateen_Branch_Logins_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens Arabic and special characters correctly
fputs($out, "\xEF\xBB\xBF");

// Header
fputcsv($out, ['Branch #', 'Branch Name', 'Branch Code', 'Role', 'Login Email', 'Password', 'Phone', 'Portal Login URL']);

$loginUrl = 'https://sheeralateen.fix4.in/login.php';

// 1. Super Admin row
if ($superAdmin) {
    fputcsv($out, [
        '-',
        'Head Office (Admin)',
        'ADMIN',
        'Super Admin',
        $superAdmin['email'],
        'sheeraadmin123',
        '-',
        $loginUrl
    ]);
}

// 2. Branch Manager rows
$counter = 1;
foreach ($branches as $b) {
    $email = $b['login_email'] ?? '';
    $pwd = $email ? resolvePassword($email) : 'NOT_CONFIGURED';
    
    fputcsv($out, [
        $counter++,
        $b['branch_name'],
        $b['branch_code'],
        'Branch Manager',
        $email,
        $pwd,
        $b['phone'] ?? '-',
        $loginUrl
    ]);
}

fclose($out);
exit;

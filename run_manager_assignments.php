<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== ASSIGNING BRANCH MANAGERS ===\n";

$assignments = [
    // Manager ID => Branch ID
    2  => 28, // Al Naeem Manager => Al-Naeem
    3  => 8,  // Bahrah Manager => Bahra
    4  => 19, // Al Sawari Manager => Obhur (Al Sawari, Obhur)
    8  => 10, // Al Hamadaniyyah Manager => Hamdaniya
    10 => 17, // Al Marwah Manager => Al Marwa
    12 => 6,  // An Naseem Manager => Al-Naseem
    13 => 21, // Palestine Manager => Thayseer (Palestine/Thayseer)
    14 => 13, // Quwaizah Manager => Khuwaisa-2
    15 => 18, // Almahameed Manager => Muhameed
    16 => 12, // Harazath Manager => Harasath 2
    17 => 24, // Taneem Manager => Makkah-Taneem
    18 => 26, // Jumoom Manager => Jumoom
];

$adminId = 1; // Super Admin

foreach ($assignments as $mgrId => $branchId) {
    // 1. Update user branch_id
    $db->prepare("UPDATE users SET branch_id = :bid WHERE id = :uid")->execute([':bid' => $branchId, ':uid' => $mgrId]);
    
    // 2. Update branch branch_manager_id
    $db->prepare("UPDATE branches SET branch_manager_id = :uid WHERE id = :bid")->execute([':uid' => $mgrId, ':bid' => $branchId]);

    // Fetch names for logging
    $mgrName = $db->query("SELECT name FROM users WHERE id = $mgrId")->fetchColumn();
    $branchName = $db->query("SELECT branch_name FROM branches WHERE id = $branchId")->fetchColumn();

    ActivityLogger::log($adminId, 'manager_assigned', "Manager '{$mgrName}' was assigned to branch '{$branchName}'.", $branchId);
    echo "? Assigned {$mgrName} -> {$branchName}\n";
}

echo "\n=== VERIFYING MANAGERS TABLE ===\n";
$stmt = $db->query("
    SELECT u.id, u.name, u.email, b.branch_name, b.id as branch_id
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.role = 'branch_manager'
    ORDER BY u.id ASC
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $bName = $row['branch_name'] ?: 'Unassigned';
    echo "Manager: {$row['name']} | Branch: {$bName}\n";
}

<?php
/**
 * setup_branch_logins.php
 * ---------------------------------------------------------------------------
 * Ensures every single branch in Sheera Lateen has a manager user and login.
 * Uses the exact pattern: <prefix>@321
 */
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain; charset=utf-8');

// 1. Add missing branches if not present: KAMIL, KHULAIS, HIRA
$missingBranches = [
    [
        'name' => 'Al Kamil',
        'code' => 'KAMIL',
        'address' => 'Al Kamil',
        'maps_url' => 'https://maps.app.goo.gl/nqvgiBBfzS9vSMf17',
        'email' => 'kamil@sheeralateen.com',
        'user_id' => 5
    ],
    [
        'name' => 'Khulais',
        'code' => 'KHULAIS',
        'address' => 'Khulais',
        'maps_url' => 'https://maps.app.goo.gl/ixWCXZpEaKoScHyZ6',
        'email' => 'khulais@sheeralateen.com',
        'user_id' => 6
    ],
    [
        'name' => 'Hira St',
        'code' => 'HIRA',
        'address' => 'Hira St, Jeddah',
        'maps_url' => 'https://maps.app.goo.gl/fSr97uDoH63vJdmt7',
        'email' => 'hira@sheeralateen.com',
        'user_id' => 9
    ]
];

foreach ($missingBranches as $mb) {
    $stmt = $db->prepare("SELECT id FROM branches WHERE branch_code = :code");
    $stmt->execute([':code' => $mb['code']]);
    $branch = $stmt->fetch();

    if (!$branch) {
        $ins = $db->prepare("
            INSERT INTO branches (branch_name, branch_code, address, maps_url, email, branch_manager_id, status)
            VALUES (:name, :code, :addr, :maps, :email, :uid, 'active')
        ");
        $ins->execute([
            ':name' => $mb['name'],
            ':code' => $mb['code'],
            ':addr' => $mb['address'],
            ':maps' => $mb['maps_url'],
            ':email' => $mb['email'],
            ':uid' => $mb['user_id']
        ]);
        $branchId = (int)$db->lastInsertId();
        echo "Inserted branch {$mb['name']} (ID: $branchId)\n";
    } else {
        $branchId = (int)$branch['id'];
        $db->prepare("UPDATE branches SET branch_manager_id = :uid WHERE id = :bid")->execute([
            ':uid' => $mb['user_id'],
            ':bid' => $branchId
        ]);
    }

    // Link user to branch
    $db->prepare("UPDATE users SET branch_id = :bid WHERE id = :uid")->execute([
        ':bid' => $branchId,
        ':uid' => $mb['user_id']
    ]);
}

// 2. Map of branches needing new manager users
$newManagers = [
    'ALAJAWEED'      => ['name' => 'Al Ajaweed',           'email' => 'ajaweed@sheeralateen.com',     'prefix' => 'ajaweed'],
    'ALQARNIA'       => ['name' => 'Al Qarnia',            'email' => 'qarnia@sheeralateen.com',      'prefix' => 'qarnia'],
    'ALSAMIR2'       => ['name' => 'Al Samir 2',           'email' => 'alsamir2@sheeralateen.com',    'prefix' => 'alsamir2'],
    'ALSAMIR3'       => ['name' => 'Al Samir-3',           'email' => 'alsamir3@sheeralateen.com',    'prefix' => 'alsamir3'],
    'ALREHELI'       => ['name' => 'Al-Reheli',            'email' => 'reheli@sheeralateen.com',      'prefix' => 'reheli'],
    'FADEYLAH'       => ['name' => 'Fadeylah',             'email' => 'fadeylah@sheeralateen.com',    'prefix' => 'fadeylah'],
    'HARASATH1'      => ['name' => 'Harasath 1',           'email' => 'harasath1@sheeralateen.com',   'prefix' => 'harasath1'],
    'LULUAMEERFAWAS' => ['name' => 'Lulu - Ameer Fawas',   'email' => 'amrfawas@sheeralateen.com',    'prefix' => 'amrfawas'],
    'LULUJAMIA'      => ['name' => 'Lulu-Jamia',           'email' => 'jamia@sheeralateen.com',       'prefix' => 'jamia'],
    'LULURUWAIS'     => ['name' => 'Lulu-Ruwais',          'email' => 'ruwais@sheeralateen.com',      'prefix' => 'ruwais'],
    'WADIMARIKH'     => ['name' => 'Wadi Marikh',          'email' => 'wadimarikh@sheeralateen.com',  'prefix' => 'wadimarikh'],
    'KAKIA'          => ['name' => 'Kakia',                'email' => 'kakia@sheeralateen.com',       'prefix' => 'kakia'],
    'ALRIYADHOSFAN'  => ['name' => 'Al-Riyadh (Osfan)',    'email' => 'alriyadh@sheeralateen.com',    'prefix' => 'alriyadh'],
    'SANABEL'        => ['name' => 'Sanabel',              'email' => 'sanabel@sheeralateen.com',     'prefix' => 'sanabel'],
];

foreach ($newManagers as $code => $info) {
    // Find branch
    $stmt = $db->prepare("SELECT id, branch_manager_id FROM branches WHERE branch_code = :code AND deleted_at IS NULL");
    $stmt->execute([':code' => $code]);
    $branch = $stmt->fetch();
    if (!$branch) continue;

    $branchId = (int)$branch['id'];

    // Check if user already exists
    $uStmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $uStmt->execute([':email' => $info['email']]);
    $user = $uStmt->fetch();

    $pwd = $info['prefix'] . '@321';
    $hash = password_hash($pwd, PASSWORD_DEFAULT);

    if ($user) {
        $userId = (int)$user['id'];
        // Update user
        $db->prepare("UPDATE users SET branch_id = :bid, password = :pwd, status = 'active' WHERE id = :uid")->execute([
            ':bid' => $branchId,
            ':pwd' => $hash,
            ':uid' => $userId
        ]);
        echo "Updated existing user for {$info['name']} (ID: $userId)\n";
    } else {
        // Insert new manager user
        $ins = $db->prepare("
            INSERT INTO users (name, email, password, role, branch_id, status)
            VALUES (:name, :email, :pwd, 'branch_manager', :bid, 'active')
        ");
        $ins->execute([
            ':name' => $info['name'] . ' Manager',
            ':email' => $info['email'],
            ':pwd' => $hash,
            ':bid' => $branchId
        ]);
        $userId = (int)$db->lastInsertId();
        echo "Created new user for {$info['name']} (ID: $userId)\n";
    }

    // Link back to branch
    $db->prepare("UPDATE branches SET branch_manager_id = :uid WHERE id = :bid")->execute([
        ':uid' => $userId,
        ':bid' => $branchId
    ]);
}

echo "\nAll branches now have active manager logins configured.\n";

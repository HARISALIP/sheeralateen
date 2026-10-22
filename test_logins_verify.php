<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$testAccounts = [
    ['admin@sheeralateen.com', 'sheeraadmin123'],
    ['safa@sheeralateen.com', 'safa@321'],
    ['sheera_jumoom@sheeralateen.com', 'jumoom123'],
    ['ajaweed@sheeralateen.com', 'ajaweed@321'],
    ['kamil@sheeralateen.com', 'kamil@321'],
    ['khulais@sheeralateen.com', 'khulais@321'],
    ['hira@sheeralateen.com', 'hira@321'],
    ['sanabel@sheeralateen.com', 'sanabel@321']
];

header('Content-Type: text/plain; charset=utf-8');
foreach ($testAccounts as [$email, $password]) {
    $stmt = $db->prepare("SELECT id, name, role, password FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch();
    if (!$u) {
        echo "FAIL: $email not found\n";
    } elseif (password_verify($password, $u['password'])) {
        echo "SUCCESS: $email verified correctly ({$u['role']})\n";
    } else {
        echo "FAIL: $email password mismatch\n";
    }
}

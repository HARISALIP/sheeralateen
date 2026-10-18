<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "Current currency_symbol: " . get_setting($db, 'currency_symbol') . "\n";

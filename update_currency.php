<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

save_setting($db, 'currency_symbol', 'SAR');
echo "Updated currency_symbol to: " . get_setting($db, 'currency_symbol') . "\n";

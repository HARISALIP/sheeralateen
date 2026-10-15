<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$db->query("UPDATE orders SET 
    current_status = 'Cancelled', 
    leajlak_status = 'Canceled', 
    leajlak_order_id = '9b9d1b26-56e6-4a99-9bf8-8b87539531f7', 
    leajlak_captain_name = 'sayed_test Rider account ', 
    leajlak_captain_phone = '889112299' 
WHERE id = 4");
echo "Restored order #4 back to original state!\n";

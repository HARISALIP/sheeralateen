<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT id, order_number, customer_name, customer_phone, total_amount, current_status, created_at FROM orders ORDER BY id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: text/plain; charset=utf-8');
foreach ($rows as $r) {
    echo "ID: {$r['id']} | {$r['order_number']} | {$r['customer_name']} | {$r['customer_phone']} | {$r['total_amount']} | {$r['current_status']} | {$r['created_at']}\n";
}
exit;

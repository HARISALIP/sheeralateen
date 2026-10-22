<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();
header('Content-Type: text/plain; charset=utf-8');
echo "Orders: " . $db->query("SELECT COUNT(*) FROM orders")->fetchColumn() . "\n";
echo "Order Items: " . $db->query("SELECT COUNT(*) FROM order_items")->fetchColumn() . "\n";
echo "Order Notes: " . $db->query("SELECT COUNT(*) FROM order_notes")->fetchColumn() . "\n";
echo "Order Status History: " . $db->query("SELECT COUNT(*) FROM order_status_history")->fetchColumn() . "\n";
echo "Sync Queue (Orders): " . $db->query("SELECT COUNT(*) FROM sync_queue WHERE entity_type = 'order'")->fetchColumn() . "\n";
exit;

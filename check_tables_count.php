<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

header('Content-Type: text/plain');
echo "=== TABLE COUNTS BEFORE CLEARING ===\n";
$tables = ['orders', 'order_items', 'order_status_history', 'order_notes', 'sync_queue', 'webhook_logs', 'activity_logs', 'branches', 'branch_coverage', 'users', 'system_settings'];
foreach ($tables as $t) {
    try {
        $cnt = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo str_pad($t, 25) . ": $cnt rows\n";
    } catch (Exception $e) {
        echo str_pad($t, 25) . ": Error or not found\n";
    }
}

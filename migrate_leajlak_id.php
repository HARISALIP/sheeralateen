<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/core/bootstrap.php';
    $db = Database::getConnection();
    
    try {
        $db->exec("ALTER TABLE orders ADD COLUMN leajlak_order_id VARCHAR(100) NULL AFTER leajlak_status");
        echo "Column leajlak_order_id added successfully!\n";
    } catch (Throwable $te) {
        echo "Column note: " . $te->getMessage() . "\n";
    }

    $logs = $db->query("SELECT payload FROM webhook_logs WHERE topic = 'leajlak/order_status' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach ($logs as $l) {
        $p = json_decode($l['payload'], true);
        if (!empty($p['id']) && !empty($p['dsp_order_id'])) {
            $stmt = $db->prepare("UPDATE orders SET leajlak_order_id = :dsp WHERE (shopify_order_id = :id OR order_number = :id OR order_number = :hash_id) AND (leajlak_order_id IS NULL OR leajlak_order_id = '')");
            $stmt->execute([
                ':dsp' => (string)$p['dsp_order_id'],
                ':id' => (string)$p['id'],
                ':hash_id' => '#' . $p['id']
            ]);
            $count += $stmt->rowCount();
        }
    }
    echo "Backfilled " . $count . " orders with leajlak_order_id.\n";
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
}

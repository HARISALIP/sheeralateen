<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

try {
    $stmtStr = "UPDATE orders SET leajlak_status = :lstatus, leajlak_captain_name = COALESCE(:cname, leajlak_captain_name), leajlak_captain_phone = COALESCE(:cphone, leajlak_captain_phone), updated_at = NOW() WHERE id = :id";
    $params = [':lstatus' => 'Reached Shop', ':cname' => 'John', ':cphone' => '123', ':id' => 85];
    $db->prepare($stmtStr)->execute($params);
    echo "Update successful!";
} catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage();
} catch (Throwable $t) {
    echo "Fatal Error: " . $t->getMessage();
}

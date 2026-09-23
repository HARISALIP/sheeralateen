<?php
error_reporting(0);
set_exception_handler(function($e) {
    http_response_code(500);
    error_log("FATAL: " . $e->getMessage());
    echo "Fatal Error: " . $e->getMessage();
});

$rawPayload = file_get_contents("php://input");
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$authHeader = isset($headers["authorization"]) ? trim($headers["authorization"]) : (isset($_SERVER["HTTP_AUTHORIZATION"]) ? trim($_SERVER["HTTP_AUTHORIZATION"]) : "");

require_once __DIR__ . "/../core/bootstrap.php";
$db = Database::getConnection();

$errorMessage = null;
$processed = 0;

try {
    do {
        if (!hash_equals("sheeratoken74", $authHeader)) {
            $errorMessage = "Authorization failed";
            break;
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload) || !isset($payload["id"], $payload["status"])) {
            $errorMessage = "Invalid JSON payload";
            break;
        }

        $shopifyOrderId = (string) $payload["id"];
        $leajlakStatus  = (string) $payload["status"];
        $dspOrderId     = isset($payload["dsp_order_id"]) ? (int) $payload["dsp_order_id"] : null;
        $captainName    = isset($payload["driver"]["name"]) ? (string) $payload["driver"]["name"] : null;
        $captainPhone   = isset($payload["driver"]["phone"]) ? (string) $payload["driver"]["phone"] : null;

        $statusMap = [
            "Order Accept"            => "Accepted",
            "Start Ride"              => "Accepted",
            "Reached shop"            => "Preparing",
            "Order Picked"            => "Preparing",
            "Shipped"                 => "Out For Delivery",
            "Reached Destination"     => "Out For Delivery",
            "Re Route"                => "Out For Delivery",
            "Delivered"               => "Delivered",
            "Cancel Request Accepted" => "Cancelled",
            "Canceled"                => "Cancelled",
            "Return To Foryou"        => "Returned",
            "Return To Client"        => "Returned",
            "Client Return Accepted"  => "Returned",
        ];

        $leajlakStatusMap = [];
        foreach ($statusMap as $k => $v) { $leajlakStatusMap[strtolower($k)] = $v; }
        
        if (!array_key_exists(strtolower($leajlakStatus), $leajlakStatusMap)) {
            $processed = 1;
            break;
        }

        $newLocalStatus = $leajlakStatusMap[strtolower($leajlakStatus)];

        $stmt = $db->prepare("SELECT id, order_number, current_status FROM orders WHERE (shopify_order_id = :sid OR order_number = :sid_on OR order_number LIKE :sid_like) AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([":sid" => $shopifyOrderId, ":sid_on" => $shopifyOrderId, ":sid_like" => "%" . $shopifyOrderId . "%"]);
        $order = $stmt->fetch();

        if (!$order) {
            $errorMessage = "Order not found for Shopify ID: {$shopifyOrderId}";
            break;
        }

        $localOrderId  = (int) $order["id"];
        $orderNumber   = (string) $order["order_number"];
        $currentStatus = (string) $order["current_status"];

        $finalStatusUpdate = "";
        if (in_array($newLocalStatus, ["Out For Delivery", "Delivered", "Cancelled", "Returned"])) {
            $finalStatusUpdate = "current_status = :status,";
        }
        
        $stmtStr = "UPDATE orders SET $finalStatusUpdate leajlak_status = :lstatus, leajlak_captain_name = COALESCE(:cname, leajlak_captain_name), leajlak_captain_phone = COALESCE(:cphone, leajlak_captain_phone), updated_at = NOW() WHERE id = :id";
        $params = [":lstatus" => $leajlakStatus, ":cname" => $captainName, ":cphone" => $captainPhone, ":id" => $localOrderId];
        if ($finalStatusUpdate) { $params[":status"] = $newLocalStatus; }
        $db->prepare($stmtStr)->execute($params);

        $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) VALUES (:oid, :old, :new, NULL, :notes)")->execute([
            ":oid"   => $localOrderId,
            ":old"   => $currentStatus,
            ":new"   => $newLocalStatus,
            ":notes" => "Leajlak webhook: " . $leajlakStatus . ($dspOrderId ? " (DSP order #{$dspOrderId})" : ""),
        ]);

        $queue = new SyncQueue($db);
        $queue->enqueue("order", $localOrderId, $shopifyOrderId, "push_status");
        try {
            $api  = new ShopifyService($db);
            $sync = new ShopifySyncService($db, $api, $queue);
            $sync->processQueue(5);
        } catch (Exception $syncEx) {}

        ActivityLogger::log(null, "leajlak_status_update", "Order {$orderNumber}: {$currentStatus} -> {$newLocalStatus}", null, $localOrderId);
        $processed = 1;
    } while (false);
} catch (Throwable $t) {
    $errorMessage = "FATAL EXCEPTION: " . $t->getMessage() . " at " . $t->getFile() . ":" . $t->getLine();
}

try {
    $db->prepare("INSERT INTO webhook_logs (topic, payload, processed, error_message, received_at) VALUES (:topic, :payload, :processed, :error, NOW())")->execute([
        ":topic"     => "leajlak/order_status",
        ":payload"   => $rawPayload ?: null,
        ":processed" => $processed,
        ":error"     => $errorMessage,
    ]);
} catch (PDOException $logEx) {}

http_response_code(200);
echo "OK";








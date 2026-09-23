<?php
error_reporting(0); // Suppress default output so we can capture it
// Add global exception handler for fatal errors
set_exception_handler(function(\) {
    http_response_code(500);
    echo "Fatal Error: " . \->getMessage();
});

require_once __DIR__ . '/../core/bootstrap.php';
\ = Database::getConnection();

\ = 'sheeratoken74';

// -- 1. Read Raw Payload --------------------------------------
\ = file_get_contents('php://input');
\    = getallheaders();

// Normalize headers to lowercase for reliable lookup
\ = array_change_key_case(\, CASE_LOWER);

\ = '';
if (isset(\['authorization'])) {
    \ = trim(\['authorization']);
} elseif (isset(\['HTTP_AUTHORIZATION'])) {
    \ = trim(\['HTTP_AUTHORIZATION']);
}

\ = null;
\    = 0;

try {
    do {
        if (!hash_equals(\, \)) {
            \ = 0;
            \ = 'Authorization failed (header present: '
                . (\ !== '' ? 'yes' : 'no') . ')';
            break;
        }

        \ = json_decode(\, true);
        if (!is_array(\) || !isset(\['id'], \['status'])) {
            \ = 'Invalid JSON payload: missing id or status field.';
            break;
        }

        \ = (string) \['id'];
        \  = (string) \['status'];
        \     = isset(\['dsp_order_id']) ? (int) \['dsp_order_id'] : null;
        
        \    = isset(\['driver']['name']) ? (string) \['driver']['name'] : null;
        \   = isset(\['driver']['phone']) ? (string) \['driver']['phone'] : null;

        \ = [
            'Order Accept'            => 'Accepted',
            'Start Ride'              => 'Accepted',
            'Reached shop'            => 'Preparing',
            'Order Picked'            => 'Preparing',
            'Shipped'                 => 'Out For Delivery',
            'Reached Destination'     => 'Out For Delivery',
            'Re Route'                => 'Out For Delivery',
            'Delivered'               => 'Delivered',
            'Cancel Request Accepted' => 'Cancelled',
            'Canceled'                => 'Cancelled',
            'Return To Foryou'        => 'Returned',
            'Return To Client'        => 'Returned',
            'Client Return Accepted'  => 'Returned',
        ];

        \ = [];
        foreach (\ as \ => \) { \[strtolower(\)] = \; }
        
        if (!array_key_exists(strtolower(\), \)) {
            \ = 1;
            break;
        }

        \ = \[strtolower(\)];

        \ = \->prepare("
            SELECT id, order_number, current_status, shopify_order_id
            FROM   orders
            WHERE  (shopify_order_id = :sid OR order_number = :sid OR order_number LIKE CONCAT('%', :sid, '%'))
              AND  deleted_at IS NULL
            LIMIT  1
        ");
        \->execute([':sid' => \]);
        \ = \->fetch();

        if (!\) {
            \ = "Order not found for Shopify ID: {\}";
            break;
        }

        \  = (int)    \['id'];
        \   = (string) \['order_number'];
        \ = (string) \['current_status'];

        \ = '';
        if (in_array(\, ['Out For Delivery', 'Delivered', 'Cancelled', 'Returned'])) {
            \ = "current_status = :status,";
        }
        \ = "UPDATE orders SET \ leajlak_status = :lstatus, leajlak_captain_name = COALESCE(:cname, leajlak_captain_name), leajlak_captain_phone = COALESCE(:cphone, leajlak_captain_phone), updated_at = NOW() WHERE id = :id";
        \ = [':lstatus' => \, ':cname' => \, ':cphone' => \, ':id' => \];
        if (\) { \[':status'] = \; }
        \->prepare(\)->execute(\);

        \->prepare("
            INSERT INTO order_status_history
                (order_id, old_status, new_status, changed_by, notes)
            VALUES
                (:oid, :old, :new, NULL, :notes)
        ")->execute([
            ':oid'   => \,
            ':old'   => \,
            ':new'   => \,
            ':notes' => 'Leajlak webhook: ' . \
                        . (\ ? " (DSP order #{\})" : ''),
        ]);

        \ = 1;

    } while (false);
} catch (Throwable \) {
    \ = "EXCEPTION: " . \->getMessage() . " at " . \->getFile() . ":" . \->getLine();
    error_log(\);
}

try {
    \->prepare("
        INSERT INTO webhook_logs
            (topic, payload, processed, error_message, received_at)
        VALUES
            (:topic, :payload, :processed, :error, NOW())
    ")->execute([
        ':topic'     => 'leajlak/order_status',
        ':payload'   => \ ?: null,
        ':processed' => \,
        ':error'     => \,
    ]);
} catch (PDOException \) {
    error_log('Leajlak webhook: Failed to write to webhook_logs: ' . \->getMessage());
}

http_response_code(200);
echo 'OK';

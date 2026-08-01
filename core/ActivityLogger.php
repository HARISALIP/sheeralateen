<?php
/**
 * ActivityLogger
 * ---------------------------------------------------------
 * Writes a row to activity_logs. Used across the app for auditing:
 * logins/logouts, branch created/updated/deleted, manager
 * created/updated, order assigned/reassigned, status changes,
 * settings updates, etc.
 *
 * Logging failures are swallowed (only written to the PHP error
 * log) so that a logging problem never breaks the actual feature
 * the user is trying to use.
 */
class ActivityLogger
{
    public static function log(
        ?int $userId,
        string $action,
        ?string $description = null,
        ?int $branchId = null,
        ?int $orderId = null
    ): void {
        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                INSERT INTO activity_logs
                    (user_id, branch_id, order_id, action, description, ip_address, user_agent)
                VALUES
                    (:user_id, :branch_id, :order_id, :action, :description, :ip_address, :user_agent)
            ");

            $stmt->execute([
                ':user_id'     => $userId,
                ':branch_id'   => $branchId,
                ':order_id'    => $orderId,
                ':action'      => $action,
                ':description' => $description,
                ':ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Exception $e) {
            error_log('ActivityLogger failed: ' . $e->getMessage());
        }
    }
}

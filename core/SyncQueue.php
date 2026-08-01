<?php
/**
 * core/SyncQueue.php
 * ---------------------------------------------------------
 * Manages the sync_queue table for asynchronous two-way sync jobs.
 * This ensures reliability during temporary API failures and
 * prevents long-running synchronous API calls in the UI.
 */
class SyncQueue
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Enqueue a new sync job.
     */
    public function enqueue(string $entityType, int $localEntityId, ?string $remoteEntityId, string $action, ?array $payload = null): void
    {
        // Prevent exact duplicates that are still pending/running
        $stmt = $this->db->prepare("
            SELECT id FROM sync_queue 
            WHERE entity_type = :entity_type 
              AND local_entity_id = :local_entity_id 
              AND action = :action 
              AND status IN ('pending', 'running')
            LIMIT 1
        ");
        $stmt->execute([
            ':entity_type' => $entityType,
            ':local_entity_id' => $localEntityId,
            ':action' => $action
        ]);
        
        if ($stmt->fetchColumn()) {
            return; // Already queued
        }

        $stmt = $this->db->prepare("
            INSERT INTO sync_queue (entity_type, local_entity_id, remote_entity_id, action, payload, status)
            VALUES (:entity_type, :local_entity_id, :remote_entity_id, :action, :payload, 'pending')
        ");
        $stmt->execute([
            ':entity_type' => $entityType,
            ':local_entity_id' => $localEntityId,
            ':remote_entity_id' => $remoteEntityId,
            ':action' => $action,
            ':payload' => $payload ? json_encode($payload) : null
        ]);
    }

    /**
     * Fetch pending jobs that are ready to run (including retries).
     * Locks them by setting status to 'running' to prevent duplicate cron runs processing them.
     */
    public function getAndLockPending(int $limit = 20): array
    {
        $this->db->beginTransaction();

        try {
            // Get jobs
            $stmt = $this->db->prepare("
                SELECT * FROM sync_queue
                WHERE status = 'pending'
                   OR (status = 'failed' AND retry_count < 5 AND next_retry_at <= NOW())
                ORDER BY created_at ASC
                LIMIT :limit
                FOR UPDATE SKIP LOCKED
            ");
            // Workaround for LIMIT with parameters in some PDO setups
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $jobs = $stmt->fetchAll();

            if (empty($jobs)) {
                $this->db->commit();
                return [];
            }

            // Lock them
            $ids = array_column($jobs, 'id');
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $updateStmt = $this->db->prepare("UPDATE sync_queue SET status = 'running' WHERE id IN ($inQuery)");
            $updateStmt->execute($ids);

            $this->db->commit();
            
            // decode json payload
            foreach ($jobs as &$job) {
                if ($job['payload']) {
                    $job['payload'] = json_decode($job['payload'], true);
                }
            }
            return $jobs;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mark a job as successfully completed.
     */
    public function markCompleted(int $jobId): void
    {
        $stmt = $this->db->prepare("UPDATE sync_queue SET status = 'completed', error_message = NULL WHERE id = :id");
        $stmt->execute([':id' => $jobId]);
    }

    /**
     * Mark a job as failed, increment retry count, set next retry.
     */
    public function markFailed(int $jobId, string $errorMessage): void
    {
        $stmt = $this->db->prepare("
            UPDATE sync_queue 
            SET status = 'failed',
                error_message = :error_message,
                retry_count = retry_count + 1,
                next_retry_at = DATE_ADD(NOW(), INTERVAL (POWER(2, retry_count) * 5) MINUTE) -- Exponential backoff (5, 10, 20, 40...)
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $jobId,
            ':error_message' => $errorMessage
        ]);
    }

    /**
     * Stats for dashboard
     */
    public function getStats(): array
    {
        return [
            'pending' => (int) $this->db->query("SELECT COUNT(*) FROM sync_queue WHERE status = 'pending'")->fetchColumn(),
            'failed'  => (int) $this->db->query("SELECT COUNT(*) FROM sync_queue WHERE status = 'failed'")->fetchColumn(),
        ];
    }
}

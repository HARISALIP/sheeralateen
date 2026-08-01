-- ============================================================
-- Phase 4: Shopify Integration Migration
-- Apply this to an existing database.
-- Safe to run multiple times (idempotent).
-- ============================================================

-- 1. Add missing columns to orders table safely
DELIMITER $$
CREATE PROCEDURE `AddShopifyColumnsIfNotExist`()
BEGIN
    DECLARE _count INT;
    
    SELECT COUNT(*) INTO _count 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'orders' 
      AND COLUMN_NAME = 'shopify_order_number'
      AND TABLE_SCHEMA = DATABASE();
      
    IF _count = 0 THEN
        ALTER TABLE orders
          ADD COLUMN shopify_order_number VARCHAR(50) NULL AFTER shopify_order_id,
          ADD COLUMN shopify_financial_status VARCHAR(30) NULL AFTER shopify_synced_at,
          ADD COLUMN shopify_fulfillment_status VARCHAR(30) NULL AFTER shopify_financial_status,
          ADD COLUMN sync_source TINYINT(1) NOT NULL DEFAULT 0 AFTER shopify_fulfillment_status,
          ADD COLUMN sync_status ENUM('synced', 'waiting', 'failed') NOT NULL DEFAULT 'synced' AFTER sync_source;
    END IF;
END$$
DELIMITER ;

CALL AddShopifyColumnsIfNotExist();
DROP PROCEDURE AddShopifyColumnsIfNotExist;

-- 2. Sync queue table
CREATE TABLE IF NOT EXISTS sync_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    local_entity_id INT UNSIGNED NOT NULL,
    remote_entity_id VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL,
    payload JSON NULL,
    status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    next_retry_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_sync_queue_status (status),
    INDEX idx_sync_queue_next_retry (next_retry_at),
    INDEX idx_sync_queue_entity (entity_type, local_entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Webhook logs table
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(100) NOT NULL,
    payload JSON NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_webhook_topic (topic),
    INDEX idx_webhook_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Settings seed for new keys
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
('shopify_api_token', ''),
('shopify_api_version', '2026-04'),
('sync_interval_minutes', '5');

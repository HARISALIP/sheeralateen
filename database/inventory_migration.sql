-- ============================================================
-- Phase 5: Branch Inventory Management & Shopify Synchronization
-- Safe, additive migration (does not modify existing tables)
-- Compatible with MySQL 5.7+ / MariaDB (Hostinger phpMyAdmin)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. PRODUCTS CATALOG
-- Cached list of products & variants from Shopify
CREATE TABLE IF NOT EXISTS products_catalog (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shopify_product_id          BIGINT UNSIGNED NULL,
    shopify_variant_id          BIGINT UNSIGNED NULL,
    shopify_inventory_item_id   BIGINT UNSIGNED NULL,
    handle                      VARCHAR(150) NOT NULL,
    title                       VARCHAR(255) NOT NULL,
    variant_title               VARCHAR(150) NULL DEFAULT 'Default Title',
    sku                         VARCHAR(100) NULL,
    barcode                     VARCHAR(100) NULL,
    price                       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_url                   TEXT NULL,
    status                      VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_catalog_handle (handle),
    INDEX idx_catalog_sku (sku),
    INDEX idx_catalog_variant (shopify_variant_id),
    INDEX idx_catalog_inv_item (shopify_inventory_item_id),
    INDEX idx_catalog_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BRANCH INVENTORY
-- Per-branch, per-product inventory tracking for ALL physical branches
CREATE TABLE IF NOT EXISTS branch_inventory (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id                   INT UNSIGNED NOT NULL,
    product_id                  BIGINT UNSIGNED NOT NULL,
    available_quantity          INT NOT NULL DEFAULT 0,
    on_hand_quantity            INT NOT NULL DEFAULT 0,
    low_stock_threshold         INT NOT NULL DEFAULT 5,
    last_synced_at              DATETIME NULL,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_branch_product (branch_id, product_id),
    INDEX idx_bi_branch (branch_id),
    INDEX idx_bi_product (product_id),
    INDEX idx_bi_available (available_quantity),

    CONSTRAINT fk_bi_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_bi_product
        FOREIGN KEY (product_id) REFERENCES products_catalog(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. INVENTORY LOGS (AUDIT TRAIL)
-- Immutable record of every stock change, user, reason, and timestamp
CREATE TABLE IF NOT EXISTS inventory_logs (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id                   INT UNSIGNED NOT NULL,
    product_id                  BIGINT UNSIGNED NOT NULL,
    previous_quantity           INT NOT NULL,
    new_quantity                INT NOT NULL,
    delta                       INT NOT NULL,
    adjustment_type             ENUM('set', 'adjust', 'shopify_sync', 'order_deduction') NOT NULL,
    reason                      VARCHAR(100) NOT NULL,
    notes                       TEXT NULL,
    user_id                     INT UNSIGNED NULL,
    sync_status                 ENUM('synced', 'pending', 'failed', 'local_only') NOT NULL DEFAULT 'local_only',
    error_details               TEXT NULL,
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_inv_log_branch (branch_id),
    INDEX idx_inv_log_product (product_id),
    INDEX idx_inv_log_user (user_id),
    INDEX idx_inv_log_created (created_at),

    CONSTRAINT fk_il_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_il_product
        FOREIGN KEY (product_id) REFERENCES products_catalog(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_il_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

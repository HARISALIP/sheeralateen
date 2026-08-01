-- ============================================================
-- Multi-Branch Shopify Management System
-- PHASE 1 (Revised): Core Database Schema
-- Compatible with MySQL 5.7+ / MariaDB (Hostinger phpMyAdmin)
--
-- Revision notes:
--   - Added deleted_at (soft delete) to users, branches, orders
--   - Standardized created_at / updated_at across all tables
--   - Rebuilt activity_logs with branch_id, order_id, user_agent
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ------------------------------------------------------------
-- 1. BRANCHES TABLE
-- ------------------------------------------------------------
CREATE TABLE branches (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_name         VARCHAR(150) NOT NULL,
    branch_code         VARCHAR(20)  NOT NULL,
    shopify_location_id BIGINT UNSIGNED NULL,
    address             TEXT         NULL,
    maps_url            TEXT         NULL,
    routing_keywords    JSON         NULL,
    phone               VARCHAR(30)  NULL,
    email               VARCHAR(150) NULL,
    branch_manager_id   INT UNSIGNED NULL,          -- FK added later (circular ref with users)
    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,               -- soft delete

    UNIQUE KEY uq_branch_code (branch_code),
    UNIQUE KEY uq_branch_shopify_location (shopify_location_id),
    INDEX idx_branch_status (status),
    INDEX idx_branch_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. USERS TABLE (Super Admin + Branch Manager)
-- ------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password        VARCHAR(255) NOT NULL,          -- bcrypt hash (PHP password_hash)
    role            ENUM('super_admin','branch_manager') NOT NULL,
    branch_id       INT UNSIGNED NULL,               -- only set when role = branch_manager
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,                   -- soft delete

    UNIQUE KEY uq_user_email (email),
    INDEX idx_user_role (role),
    INDEX idx_user_branch (branch_id),
    INDEX idx_user_deleted (deleted_at),

    CONSTRAINT fk_users_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Now that users exists, add the branch -> manager foreign key
ALTER TABLE branches
    ADD CONSTRAINT fk_branches_manager
        FOREIGN KEY (branch_manager_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- ------------------------------------------------------------
-- 3. ORDERS TABLE
-- ------------------------------------------------------------
CREATE TABLE orders (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Shopify sync fields (nullable for now, populated later by integration)
    shopify_order_id    VARCHAR(50)  NULL,
    shopify_order_number VARCHAR(50) NULL,
    shopify_synced_at   DATETIME     NULL,
    shopify_financial_status VARCHAR(30) NULL,
    shopify_fulfillment_status VARCHAR(30) NULL,
    sync_source         TINYINT(1)   NOT NULL DEFAULT 0,
    sync_status         ENUM('synced', 'waiting', 'failed') NOT NULL DEFAULT 'synced',

    order_number        VARCHAR(50)  NOT NULL,
    customer_name       VARCHAR(150) NOT NULL,
    customer_phone      VARCHAR(30)  NULL,
    customer_email      VARCHAR(150) NULL,
    delivery_address    TEXT         NULL,

    total_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status      ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',

    assigned_branch_id  INT UNSIGNED NULL,
    current_status       ENUM(
                            'New','Assigned','Accepted','Preparing',
                            'Ready','Out For Delivery','Delivered',
                            'Cancelled','Returned'
                          ) NOT NULL DEFAULT 'New',

    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,               -- soft delete

    UNIQUE KEY uq_order_number (order_number),
    INDEX idx_order_shopify_id (shopify_order_id),
    INDEX idx_order_branch (assigned_branch_id),
    INDEX idx_order_status (current_status),
    INDEX idx_order_customer (customer_name),
    INDEX idx_order_phone (customer_phone),
    INDEX idx_order_created (created_at),
    INDEX idx_order_deleted (deleted_at),

    CONSTRAINT fk_orders_branch
        FOREIGN KEY (assigned_branch_id) REFERENCES branches(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. ORDER ITEMS TABLE
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id             INT UNSIGNED NOT NULL,

    -- Shopify sync fields
    shopify_line_item_id VARCHAR(50) NULL,
    sku                  VARCHAR(100) NULL,

    item_name            VARCHAR(255) NOT NULL,
    quantity             INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal             DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_item_order (order_id),

    CONSTRAINT fk_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. ORDER STATUS HISTORY TABLE
--    Every status change is logged here for a full audit trail
-- ------------------------------------------------------------
CREATE TABLE order_status_history (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    old_status      VARCHAR(30)  NULL,
    new_status      VARCHAR(30)  NOT NULL,
    changed_by      INT UNSIGNED NULL,       -- user_id, NULL if changed by system/automation
    notes           TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_history_order (order_id),
    INDEX idx_history_created (created_at),

    CONSTRAINT fk_history_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. ORDER NOTES TABLE
--    Internal notes added by Branch Managers / Super Admin
-- ------------------------------------------------------------
CREATE TABLE order_notes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    note            TEXT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_notes_order (order_id),

    CONSTRAINT fk_notes_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_notes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. SYSTEM SETTINGS TABLE (key-value, extensible)
-- ------------------------------------------------------------
CREATE TABLE system_settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(100) NOT NULL,
    setting_value   TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. ACTIVITY LOGS TABLE
--    Records important system events for auditing & debugging:
--    logins/logouts, branch created/updated/deleted, manager
--    created/updated, order assigned/reassigned, status changes,
--    settings updates, etc.
--
--    NOTE: This table intentionally has NO updated_at / deleted_at.
--    Audit log rows must be immutable and permanent — editing or
--    soft-deleting a log entry would defeat its purpose.
-- ------------------------------------------------------------
CREATE TABLE activity_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,        -- who performed the action (NULL = system)
    branch_id       INT UNSIGNED NULL,        -- related branch, if applicable
    order_id        INT UNSIGNED NULL,        -- related order, if applicable
    action          VARCHAR(150) NOT NULL,    -- e.g. 'login', 'order_assigned', 'branch_created'
    description     TEXT NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_log_user (user_id),
    INDEX idx_log_branch (branch_id),
    INDEX idx_log_order (order_id),
    INDEX idx_log_action (action),
    INDEX idx_log_created (created_at),

    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_log_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_log_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. SYNC QUEUE TABLE
--    Manages two-way sync jobs (e.g. pushing status back to Shopify)
-- ------------------------------------------------------------
CREATE TABLE sync_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- e.g. 'order', 'customer', 'product'
    local_entity_id INT UNSIGNED NOT NULL,
    remote_entity_id VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL, -- e.g. 'push_status', 'import'
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

-- ------------------------------------------------------------
-- 10. WEBHOOK LOGS TABLE
--     Prepares architecture for future webhook support
-- ------------------------------------------------------------
CREATE TABLE webhook_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(100) NOT NULL,
    payload JSON NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_webhook_topic (topic),
    INDEX idx_webhook_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- SEED DATA: Default Super Admin
-- Email: admin@yourstore.com | Password: ChangeMe123!
-- CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN
-- ------------------------------------------------------------
INSERT INTO users (name, email, password, role, status)
VALUES (
    'Super Admin',
    'admin@yourstore.com',
    '$2y$10$gOiwDNICkswOkjaG5/A2l.ylhY.8YDXQC2f7BFh/odb4L236pLd8C',
    'super_admin',
    'active'
);

-- ------------------------------------------------------------
-- SEED DATA: Sample Branch (optional — remove if not needed)
-- ------------------------------------------------------------
INSERT INTO branches (branch_name, branch_code, address, phone, email, status)
VALUES (
    'Main Branch',
    'MAIN01',
    'Sample Address, City',
    '+000000000',
    'mainbranch@yourstore.com',
    'active'
);

-- ============================================================
-- Migration: Add User Remember Tokens Table
-- Supports persistent "Remember Me" login sessions (30 days)
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `selector`    VARCHAR(64) NOT NULL,
    `token_hash`  VARCHAR(64) NOT NULL,
    `expires_at`  DATETIME NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_remember_selector` (`selector`),
    INDEX `idx_remember_user_id` (`user_id`),
    INDEX `idx_remember_expires_at` (`expires_at`),
    CONSTRAINT `fk_remember_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


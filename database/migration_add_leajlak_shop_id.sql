-- ============================================================
-- migration_add_leajlak_shop_id.sql
-- Sheeralateen - Add Leajlak Shop ID to Branches Table
-- ============================================================
-- RUN ONCE in phpMyAdmin on Hostinger:
--   hPanel -> Databases -> phpMyAdmin -> select database
--   -> SQL tab -> paste contents -> Go
--
-- Safe to re-run: IF NOT EXISTS prevents duplicate column errors.
-- ============================================================

ALTER TABLE branches
    ADD COLUMN IF NOT EXISTS leajlak_shop_id VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'Leajlak Shop ID for branch-specific pickups'
        AFTER shopify_location_id;

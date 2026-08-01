-- ============================================================
-- Admin Modules: Required SQL Statements
-- Run these ONCE in phpMyAdmin or your MySQL client.
-- All statements are idempotent (safe to re-run).
-- ============================================================

-- ------------------------------------------------------------
-- 1. SEED DEFAULT SYSTEM SETTINGS
--    Uses INSERT IGNORE — safe to run on an existing table.
--    Does NOT overwrite values you have already saved.
-- ------------------------------------------------------------
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
  ('company_name',         'Sheera Lateen'),
  ('company_email',        ''),
  ('company_phone',        ''),
  ('company_address',      ''),
  ('company_logo',         ''),
  ('company_favicon',      ''),
  ('currency_symbol',      '₹'),
  ('orders_per_page',      '25'),
  ('timezone',             'Asia/Kolkata'),
  ('branch_auto_assign',   '0'),
  ('shopify_store_url',    '');

-- ------------------------------------------------------------
-- 2. VERIFY TABLES EXIST (informational — no changes)
--    Run SHOW TABLES; to confirm these are present:
--      branches, users, orders, order_items,
--      order_status_history, order_notes,
--      system_settings, activity_logs
-- ------------------------------------------------------------

-- ============================================================
-- NO ALTER TABLE STATEMENTS ARE REQUIRED.
-- All needed columns exist in the original schema.sql.
-- ============================================================

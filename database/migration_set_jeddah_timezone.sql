-- ============================================================
-- Migration: Set Application Timezone to Jeddah (UTC+3)
-- ============================================================

INSERT INTO settings (setting_key, setting_value)
VALUES ('timezone', 'Asia/Riyadh')
ON DUPLICATE KEY UPDATE setting_value = 'Asia/Riyadh';


-- Migration to add leajlak_api_token to system_settings
INSERT INTO system_settings (setting_key, setting_value) 
VALUES ('leajlak_api_token', '') 
ON DUPLICATE KEY UPDATE setting_value = setting_value;

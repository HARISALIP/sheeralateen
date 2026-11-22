ALTER TABLE orders 
ADD COLUMN leajlak_captain_name VARCHAR(100) NULL AFTER current_status,
ADD COLUMN leajlak_captain_phone VARCHAR(50) NULL AFTER leajlak_captain_name;

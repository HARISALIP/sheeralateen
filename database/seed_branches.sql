-- ==========================================================
-- Sheera Lateen Branch Seeder
-- Generated from Client Excel Data
-- ==========================================================

-- 1. Remove old placeholder branches
DELETE FROM branches WHERE branch_name IN ('Main Branch', 'b mart');

-- 2. Insert real branches
INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al Ajaweed', 'ALAJAWEED', 'Jeddah', 'https://maps.app.goo.gl/S4VJS3XEDnU2jAQp9', '0543448940', 'sheera_ajaweed@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al Qarnia', 'ALQARNIA', 'Jeddah', 'https://maps.app.goo.gl/17J7UrbYBaGi97Nw6', '0559085412', 'sheera_alqarniya@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al Samir 2', 'ALSAMIR2', 'Jeddah', 'https://maps.app.goo.gl/PASk54kAdgdB5EHY9', '0543449165', 'sheera_alsamir2@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al Samir-3', 'ALSAMIR3', 'Jeddah', 'https://maps.app.goo.gl/afkVH2jT9YU5Wixk9', '0557635369', 'sheera_alsamir3@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al-Naseem', 'ALNASEEM', 'Jeddah', 'https://maps.app.goo.gl/fqWnRskTJ3n47bEB7', '0555016370', 'sheera_alnaseem@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al-Reheli', 'ALREHELI', 'Jeddah', 'https://maps.app.goo.gl/DDQE7gyzRY7aG3P86', '0538563025', 'sheera_reheli@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Bahra', 'BAHRA', 'Jeddah', 'https://maps.app.goo.gl/MPfqMyXB6JwaFS6p9', '0538627250', 'sheera_bahra@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Fadeylah', 'FADEYLAH', 'Jeddah', 'https://maps.app.goo.gl/YNvjnwrd9wTVpY6F9', '0551253217', 'sheera_fadeylah@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Hamdaniya', 'HAMDANIYA', 'Jeddah', 'https://maps.app.goo.gl/AiJSEzvJArMBaoN18', '0543450643', 'sheera_hamdaniya@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Harasath 1', 'HARASATH1', 'Jeddah', 'https://maps.app.goo.gl/P78RnBYiPTLrz8MF6', '0538275440', 'sheera_harasath@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Harasath 2', 'HARASATH2', 'Jeddah', 'https://maps.app.goo.gl/2QBsJFeqMQcPNrEo9', '0559057803', 'sheera_harasath2@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Khuwaisa-2', 'KHUWAISA2', 'Jeddah', 'https://maps.app.goo.gl/r9gUtFaSpP8bL5Wc8', '0558839972', 'sheera_kuwaisa2@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Lulu - Ameer Fawas', 'LULUAMEERFAWAS', 'Jeddah', 'https://maps.app.goo.gl/QLkDRGAkXhfkjwhy5', '0558002083', 'sheera_amrfvs_lulu@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Lulu-Jamia', 'LULUJAMIA', 'Jeddah', 'https://maps.app.goo.gl/X1TXdJz4iqujhPHM8?g_st=iw', '0552910405', 'sheera_jamia_lulu@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Lulu-Ruwais', 'LULURUWAIS', 'Jeddah', 'https://maps.app.goo.gl/vRdwNQJEcR9mm5Ys6', '0562948769', 'sheera_ruwais_lulu@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al Marwa', 'ALMARWA', 'Jeddah', 'https://maps.app.goo.gl/Rnmro8W7vbfovPpBA', '0559004483', 'sheera_almarwa@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Muhameed', 'MUHAMEED', 'Jeddah', 'https://maps.app.goo.gl/Ys5auGQ3b3E8u9ev5', '0551248241', 'sheera_muhameed@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Obhur', 'OBHUR', 'Jeddah', 'https://maps.app.goo.gl/TNweLeFzWFtBrtBf9', '0559712549', 'sheera_obhur@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Safa', 'SAFA', 'Jeddah', 'https://maps.app.goo.gl/GuCGq4nhVEuCyFqu9', '0500015716', 'sheera_safa@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Thayseer', 'THAYSEER', 'Jeddah', 'https://maps.app.goo.gl/fuwrLS8QFns8vHNb9', '0555014184', 'sheera_thayseer@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Wadi Marikh', 'WADIMARIKH', 'Jeddah', 'https://maps.app.goo.gl/Zb4HUTTdxLAb81D2A', '0559006986', 'sheera_wadimarikh@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Kakia', 'KAKIA', 'Makkah', 'https://maps.app.goo.gl/J2UbY7xaA9o7RydeA', '0543450612', 'sheera_kakia@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Makkah-Taneem', 'MAKKAHTANEEM', 'Makkah', 'https://maps.app.goo.gl/afkVH2jT9YU5Wixk9', '0559653785', 'sheera_taneemmakkha@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al-Riyadh (Osfan)', 'ALRIYADHOSFAN', 'Jumoom', 'https://maps.app.goo.gl/khToJ2T5kWLHMvjU8', '0556231646', 'sheera_alriyadh@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Jumoom', 'JUMOOM', 'Jumoom', 'https://maps.app.goo.gl/CV1qecKWoMCg196P9', '0551256118', 'sheera_jumoom@sheeralateen.com', 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Sanabel', 'SANABEL', 'Jeddah', 'https://maps.app.goo.gl/6azF5Z3RWEahzkmH9', '0543448423', NULL, 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Al-Naeem', 'ALNAEEM', 'Jeddah', 'https://maps.app.goo.gl/3ke5WisuP6mXzJAVA', '0540997448', NULL, 'active', NULL, NULL);

INSERT IGNORE INTO branches 
  (branch_name, branch_code, address, maps_url, phone, email, status, shopify_location_id, branch_manager_id)
VALUES 
  ('Gharan', 'GHARAN', 'Asfan', 'https://maps.app.goo.gl/vAcndcssxDNeEXW19', '0545394868', NULL, 'active', NULL, NULL);


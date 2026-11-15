-- ============================================================
-- Migration: Add GPS Coordinates to Branches
-- Compatible with MySQL 5.7+ / MariaDB (Hostinger phpMyAdmin)
-- Exact coordinates extracted from official Google Maps URLs
-- ============================================================

-- STEP 1: Add coordinate columns (idempotent — won't error if already added)
ALTER TABLE branches
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10, 8) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL DEFAULT NULL;

-- Add spatial index for efficient proximity queries
ALTER TABLE branches
    ADD INDEX IF NOT EXISTS idx_branch_location (latitude, longitude);

-- ============================================================
-- STEP 2: Seed exact coordinates per branch from Google Maps
-- ============================================================

UPDATE branches SET latitude = 21.408472, longitude = 39.296599 WHERE branch_code = 'ALAJAWEED'; -- Al Ajaweed (Jeddah)
UPDATE branches SET latitude = 21.307772, longitude = 39.260652 WHERE branch_code = 'ALQARNIA'; -- Al Qarnia (Jeddah)
UPDATE branches SET latitude = 21.578688, longitude = 39.240313 WHERE branch_code = 'ALSAMIR2'; -- Al Samir 2 (Jeddah)
UPDATE branches SET latitude = 21.514599, longitude = 39.787411 WHERE branch_code = 'ALSAMIR3'; -- Al Samir-3 (Jeddah)
UPDATE branches SET latitude = 21.517475, longitude = 39.230993 WHERE branch_code = 'ALNASEEM'; -- Al-Naseem (Jeddah)
UPDATE branches SET latitude = 21.798262, longitude = 39.139203 WHERE branch_code = 'ALREHELI'; -- Al-Reheli (Jeddah)
UPDATE branches SET latitude = 21.401486, longitude = 39.450685 WHERE branch_code = 'BAHRA'; -- Bahra (Jeddah)
UPDATE branches SET latitude = 21.308511, longitude = 39.262007 WHERE branch_code = 'FADEYLAH'; -- Fadeylah (Jeddah)
UPDATE branches SET latitude = 21.741742, longitude = 39.191553 WHERE branch_code = 'HAMDANIYA'; -- Hamdaniya (Jeddah)
UPDATE branches SET latitude = 21.484197, longitude = 39.369716 WHERE branch_code = 'HARASATH1'; -- Harasath 1 (Jeddah)
UPDATE branches SET latitude = 21.476937, longitude = 39.352926 WHERE branch_code = 'HARASATH2'; -- Harasath 2 (Jeddah)
UPDATE branches SET latitude = 21.496637, longitude = 39.270339 WHERE branch_code = 'KHUWAISA2'; -- Khuwaisa-2 (Jeddah)
UPDATE branches SET latitude = 21.437728, longitude = 39.279921 WHERE branch_code = 'LULUAMEERFAWAS'; -- Lulu - Ameer Fawas (Jeddah)
UPDATE branches SET latitude = 21.481900, longitude = 39.237800 WHERE branch_code = 'LULUJAMIA'; -- Lulu-Jamia (Jeddah)
UPDATE branches SET latitude = 21.516864, longitude = 39.181480 WHERE branch_code = 'LULURUWAIS'; -- Lulu-Ruwais (Jeddah)
UPDATE branches SET latitude = 21.607286, longitude = 39.211221 WHERE branch_code = 'ALMARWA'; -- Al Marwa (Jeddah)
UPDATE branches SET latitude = 21.442582, longitude = 39.394180 WHERE branch_code = 'MUHAMEED'; -- Muhameed (Jeddah)
UPDATE branches SET latitude = 21.788974, longitude = 39.095512 WHERE branch_code = 'OBHUR'; -- Obhur (Jeddah)
UPDATE branches SET latitude = 21.571666, longitude = 39.220164 WHERE branch_code = 'SAFA'; -- Safa (Jeddah)
UPDATE branches SET latitude = 21.554202, longitude = 39.286113 WHERE branch_code = 'THAYSEER'; -- Thayseer (Jeddah)
UPDATE branches SET latitude = 21.556859, longitude = 39.291992 WHERE branch_code = 'WADIMARIKH'; -- Wadi Marikh (Jeddah)
UPDATE branches SET latitude = 21.372587, longitude = 39.803877 WHERE branch_code = 'KAKIA'; -- Kakia (Makkah)
UPDATE branches SET latitude = 21.514599, longitude = 39.787411 WHERE branch_code = 'MAKKAHTANEEM'; -- Makkah-Taneem (Makkah)
UPDATE branches SET latitude = 21.845797, longitude = 39.202736 WHERE branch_code = 'ALRIYADHOSFAN'; -- Al-Riyadh (Osfan) (Jumoom)
UPDATE branches SET latitude = 21.614700, longitude = 39.693727 WHERE branch_code = 'JUMOOM'; -- Jumoom (Jumoom)
UPDATE branches SET latitude = 21.408390, longitude = 39.259584 WHERE branch_code = 'SANABEL'; -- Sanabel (Jeddah)
UPDATE branches SET latitude = 21.618432, longitude = 39.141007 WHERE branch_code = 'ALNAEEM'; -- Al-Naeem (Jeddah)
UPDATE branches SET latitude = 21.975562, longitude = 39.361438 WHERE branch_code = 'GHARAN'; -- Gharan (Asfan)

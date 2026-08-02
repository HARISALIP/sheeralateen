-- ============================================================
-- Migration: Add GPS Coordinates to Branches
-- Run once in phpMyAdmin on Hostinger
-- ============================================================
-- Adds latitude & longitude columns, then seeds approximate
-- coordinates for all 28 active Sheeralateen branches.
-- Coordinates are based on known district locations across
-- Jeddah, Makkah, Jumoom, and Asfan.
-- Fine-tune any coordinate by opening Google Maps,
-- right-clicking the exact branch entrance → "Copy coordinates".
-- ============================================================

-- STEP 1: Add coordinate columns (idempotent — won't error if already added)
ALTER TABLE branches
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10, 8) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL DEFAULT NULL;

-- Add spatial index for efficient proximity queries
ALTER TABLE branches
    ADD INDEX IF NOT EXISTS idx_branch_location (latitude, longitude);

-- ============================================================
-- STEP 2: Seed approximate coordinates per branch
-- Source: district-level Google Maps lookups
-- ============================================================

-- Jeddah — Al Ajaweed (Al Ajaweed district, east Jeddah)
UPDATE branches SET latitude = 21.5433, longitude = 39.1728 WHERE branch_code = 'ALAJAWEED';

-- Jeddah — Al Qarnia (Al Qurniyah district)
UPDATE branches SET latitude = 21.4908, longitude = 39.2225 WHERE branch_code = 'ALQARNIA';

-- Jeddah — Al Samir 2 (Al Samir district)
UPDATE branches SET latitude = 21.5169, longitude = 39.1883 WHERE branch_code = 'ALSAMIR2';

-- Jeddah — Al Samir 3 (Al Samir district, nearby)
UPDATE branches SET latitude = 21.5210, longitude = 39.1855 WHERE branch_code = 'ALSAMIR3';

-- Jeddah — Al-Naseem (Al Naseem district, east)
UPDATE branches SET latitude = 21.4975, longitude = 39.2347 WHERE branch_code = 'ALNASEEM';

-- Jeddah — Al-Reheli (Al Rehily district, north)
UPDATE branches SET latitude = 21.5628, longitude = 39.1543 WHERE branch_code = 'ALREHELI';

-- Bahra (on the Jeddah–Makkah highway, ~30 km east of Jeddah)
UPDATE branches SET latitude = 21.5831, longitude = 39.4119 WHERE branch_code = 'BAHRA';

-- Jeddah — Fadeylah (Al Faisaliah / Fadeylah district, south)
UPDATE branches SET latitude = 21.4751, longitude = 39.2289 WHERE branch_code = 'FADEYLAH';

-- Jeddah — Hamdaniya (Hamdan district, central)
UPDATE branches SET latitude = 21.5547, longitude = 39.1822 WHERE branch_code = 'HAMDANIYA';

-- Jeddah — Harasath 1 (Al Harasath district)
UPDATE branches SET latitude = 21.5384, longitude = 39.1756 WHERE branch_code = 'HARASATH1';

-- Jeddah — Harasath 2 (Al Harasath district, nearby)
UPDATE branches SET latitude = 21.5401, longitude = 39.1790 WHERE branch_code = 'HARASATH2';

-- Jeddah — Khuwaisa-2 (Al Khuwaiz district, north-west)
UPDATE branches SET latitude = 21.5282, longitude = 39.1629 WHERE branch_code = 'KHUWAISA2';

-- Jeddah — Lulu Ameer Fawas (Prince Fawaz hypermarket area, north)
UPDATE branches SET latitude = 21.5711, longitude = 39.2011 WHERE branch_code = 'LULUAMEERFAWAS';

-- Jeddah — Lulu Jamia (Al Jamiah district, near university)
UPDATE branches SET latitude = 21.5021, longitude = 39.1906 WHERE branch_code = 'LULUJAMIA';

-- Jeddah — Lulu Ruwais (Al Ruwais district, north)
UPDATE branches SET latitude = 21.5598, longitude = 39.1619 WHERE branch_code = 'LULURUWAIS';

-- Jeddah — Al Marwa (Al Marwa district, south)
UPDATE branches SET latitude = 21.4856, longitude = 39.2186 WHERE branch_code = 'ALMARWA';

-- Jeddah — Muhameed (Al Muhammediyah district, south-west)
UPDATE branches SET latitude = 21.4697, longitude = 39.2104 WHERE branch_code = 'MUHAMEED';

-- Jeddah — Obhur (Al Obhur Al Shamaliyah, far north coastal)
UPDATE branches SET latitude = 21.6473, longitude = 39.1258 WHERE branch_code = 'OBHUR';

-- Jeddah — Safa (Al Safa district, central-south)
UPDATE branches SET latitude = 21.4808, longitude = 39.1935 WHERE branch_code = 'SAFA';

-- Jeddah — Thayseer (Al Thayseer district)
UPDATE branches SET latitude = 21.5069, longitude = 39.1879 WHERE branch_code = 'THAYSEER';

-- Jeddah — Wadi Marikh (Wadi Marikh, east Jeddah)
UPDATE branches SET latitude = 21.5167, longitude = 39.1592 WHERE branch_code = 'WADIMARIKH';

-- Jeddah — Sanabel (Al Sanabel district)
UPDATE branches SET latitude = 21.5354, longitude = 39.1683 WHERE branch_code = 'SANABEL';

-- Jeddah — Al-Naeem (Al Naeem district, east-central)
UPDATE branches SET latitude = 21.5225, longitude = 39.2103 WHERE branch_code = 'ALNAEEM';

-- Makkah — Kakia (Kakia district, north Makkah)
UPDATE branches SET latitude = 21.4547, longitude = 39.8423 WHERE branch_code = 'KAKIA';

-- Makkah — Taneem (Al Taneem area, north of Masjid al-Haram)
UPDATE branches SET latitude = 21.4658, longitude = 39.8125 WHERE branch_code = 'MAKKAHTANEEM';

-- Jumoom — Al-Riyadh (Osfan area, on Jeddah–Madinah highway)
UPDATE branches SET latitude = 21.9614, longitude = 39.3531 WHERE branch_code = 'ALRIYADHOSFAN';

-- Jumoom town centre
UPDATE branches SET latitude = 21.7978, longitude = 39.6231 WHERE branch_code = 'JUMOOM';

-- Asfan — Gharan (Gharan village, east of Jeddah)
UPDATE branches SET latitude = 21.8892, longitude = 39.4356 WHERE branch_code = 'GHARAN';

-- ============================================================
-- VERIFICATION QUERY — run after migration to confirm
-- ============================================================
-- SELECT branch_code, branch_name, latitude, longitude
-- FROM branches
-- WHERE latitude IS NULL AND status = 'active'
-- ORDER BY branch_name;
-- (Should return 0 rows if all seeded correctly)

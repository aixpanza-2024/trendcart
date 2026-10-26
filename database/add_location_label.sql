-- =============================================================================
-- Customer's chosen location: keep a readable place name with the coordinates
-- Run once in phpMyAdmin. Safe to run again.
--
-- customer_profiles.latitude/longitude already hold the location picked in
-- the navbar location chooser (or sent by the app); location_label is what
-- the navbar shows, e.g. "Kazhakootam, Thiruvananthapuram".
-- =============================================================================

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_profiles' AND COLUMN_NAME = 'location_label'),
    'SELECT "customer_profiles.location_label already exists"',
    'ALTER TABLE customer_profiles ADD COLUMN location_label VARCHAR(255) NULL AFTER longitude');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

SHOW COLUMNS FROM customer_profiles LIKE 'location%';

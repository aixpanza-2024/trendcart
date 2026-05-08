-- Migration: Handling fee + first order free delivery
-- Run once in phpMyAdmin

ALTER TABLE orders ADD COLUMN handling_fee DECIMAL(8,2) DEFAULT 0.00 AFTER delivery_zone;

-- Seed platform settings (safe to run multiple times)
INSERT INTO platform_settings (setting_key, setting_value, setting_type, description)
VALUES
    ('handling_fee',              '0',  'number',  'Handling fee charged on every order (₹). Set 0 to disable.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO platform_settings (setting_key, setting_value, setting_type, description)
VALUES
    ('first_order_free_delivery', '1',  'boolean', 'Free delivery on first order for all zones (1=yes, 0=no)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

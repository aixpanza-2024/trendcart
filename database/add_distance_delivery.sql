-- Migration: Distance-based delivery fee
-- Run once in phpMyAdmin
--
-- Delivery fee is now calculated from the distance between the shop and the
-- customer's delivery address when both have coordinates set (mobile app
-- sends the delivery address's lat/lng at checkout). When coordinates are
-- missing on either side, the existing pincode/zone system is used as a
-- fallback — nothing about that system is removed.

ALTER TABLE addresses
    ADD COLUMN latitude  DECIMAL(10,7) NULL,
    ADD COLUMN longitude DECIMAL(10,7) NULL;

INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_type, description) VALUES
    ('delivery_fee_under_20km', '29', 'number', 'Delivery fee when shop-to-customer distance is under 20km'),
    ('delivery_fee_above_20km', '49', 'number', 'Delivery fee when shop-to-customer distance is 20km or more');

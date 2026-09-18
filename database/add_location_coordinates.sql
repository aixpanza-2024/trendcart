-- Migration: Location coordinates
-- Run once in phpMyAdmin
--
-- Shops: owner selects their fixed location once via a map picker in the
--        shop dashboard (web) — no new API, just two extra columns used by
--        the existing api/shop/profile.php.
-- Customers: the mobile app captures the customer's current GPS location
--        and sends it via the new api/customer/update-location.php.

ALTER TABLE shops
    ADD COLUMN latitude  DECIMAL(10,7) NULL AFTER shop_pincode,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;

ALTER TABLE customer_profiles
    ADD COLUMN latitude            DECIMAL(10,7) NULL,
    ADD COLUMN longitude           DECIMAL(10,7) NULL,
    ADD COLUMN location_updated_at TIMESTAMP NULL;

-- Splits shops into two independent storefronts (Dress vs Cosmetics) — a shop
-- belongs to exactly one vertical, set by admin. Existing shops default to
-- 'dress' since that's the only vertical that has ever existed so far.
ALTER TABLE shops
    ADD COLUMN business_vertical ENUM('dress','cosmetics') NOT NULL DEFAULT 'dress' AFTER shop_name;

-- Lets a customer opt out of offer notifications (in-app + push) without
-- affecting order/account notifications, which aren't covered by this flag.
ALTER TABLE customer_profiles
    ADD COLUMN notifications_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER location_updated_at;

-- Free-delivery coupons
-- Run this ONCE via phpMyAdmin BEFORE deploying the code that uses it.
-- Adds a 'free_delivery' coupon type (waives the delivery charge instead of
-- taking money off the products) and a first_order_only flag (coupon works
-- only on a customer's first non-cancelled order). Existing coupons are
-- unchanged.

ALTER TABLE coupons
    MODIFY COLUMN discount_type ENUM('percentage', 'fixed', 'free_delivery') NOT NULL;

ALTER TABLE coupons
    ADD COLUMN first_order_only TINYINT(1) NOT NULL DEFAULT 0 AFTER usage_limit_per_user;

-- Optional: a ready-made first-order free delivery coupon. Remove the leading
-- "-- " from the INSERT below to create it, or create it from Admin > Coupons.
-- INSERT INTO coupons (code, description, discount_type, discount_value, min_order_value,
--                      usage_limit_per_user, first_order_only, is_active)
-- VALUES ('FIRSTFREE', 'Free delivery on your first order', 'free_delivery', 0, 0, 1, 1, 1);

-- Verify
SHOW COLUMNS FROM coupons LIKE 'discount_type';
SHOW COLUMNS FROM coupons LIKE 'first_order_only';

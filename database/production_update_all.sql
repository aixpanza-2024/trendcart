-- =============================================================================
-- Combined production update — everything found missing when comparing the
-- live notechin_trencart schema dump against this repo's migration files.
-- Safe to run once via phpMyAdmin's Import (or SQL tab) against production.
--
-- Contains, in order:
--   1) rating_migration.sql            (pre-existing gap, unrelated to appaddon)
--   2) add_delivered_at_order_items.sql (pre-existing gap, unrelated to appaddon)
--   3) fix_delivery_zone_collation.sql  (urgent bug fix)
--   4) add_super_categories.sql         (appaddon)
--   5) add_location_coordinates.sql     (appaddon)
--   6) add_distance_delivery.sql        (appaddon)
-- =============================================================================


-- =============================================================================
-- 1) rating_migration.sql
-- Prevents duplicate reviews; makes new reviews also update the shop's own
-- rating_average/total_ratings (currently only the product's rating updates).
-- =============================================================================

ALTER TABLE product_reviews
    ADD UNIQUE KEY unique_product_order_user (product_id, user_id, order_id);

DROP TRIGGER IF EXISTS after_review_insert;

DELIMITER //

CREATE TRIGGER after_review_insert
AFTER INSERT ON product_reviews
FOR EACH ROW
BEGIN
    DECLARE v_shop_id INT;

    SELECT shop_id INTO v_shop_id
    FROM products
    WHERE product_id = NEW.product_id
    LIMIT 1;

    UPDATE products
    SET rating_average = COALESCE(
            (SELECT AVG(rating) FROM product_reviews
             WHERE product_id = NEW.product_id AND is_approved = 1), 0),
        total_ratings  = (SELECT COUNT(*) FROM product_reviews
                          WHERE product_id = NEW.product_id AND is_approved = 1)
    WHERE product_id = NEW.product_id;

    UPDATE shops
    SET rating_average = COALESCE(
            (SELECT AVG(pr.rating)
             FROM product_reviews pr
             INNER JOIN products p ON pr.product_id = p.product_id
             WHERE p.shop_id = v_shop_id AND pr.is_approved = 1), 0),
        total_ratings  = (
            SELECT COUNT(*)
            FROM product_reviews pr
            INNER JOIN products p ON pr.product_id = p.product_id
            WHERE p.shop_id = v_shop_id AND pr.is_approved = 1)
    WHERE shop_id = v_shop_id;
END//

DELIMITER ;


-- =============================================================================
-- 2) add_delivered_at_order_items.sql
-- Adds delivered_at to order_items for accurate payout date tracking, and
-- backfills it for items already marked delivered.
-- =============================================================================

ALTER TABLE order_items ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER item_status;
ALTER TABLE order_items ADD INDEX idx_delivered_at (delivered_at);

UPDATE order_items oi
INNER JOIN orders o ON oi.order_id = o.order_id
SET oi.delivered_at = COALESCE(o.delivered_at, o.order_date)
WHERE oi.item_status = 'delivered'
  AND oi.delivered_at IS NULL;


-- =============================================================================
-- 3) fix_delivery_zone_collation.sql
-- delivery_zones / delivery_pincodes are currently latin1_swedish_ci on
-- production — mismatched against the rest of the schema's utf8mb4_unicode_ci.
-- This breaks any query joining them against shops.shop_pincode.
-- =============================================================================

ALTER TABLE delivery_zones    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE delivery_pincodes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- =============================================================================
-- 4) add_super_categories.sql
-- Men / Women / Kids / Cosmetics grouping over existing parent categories.
-- =============================================================================

CREATE TABLE IF NOT EXISTS super_categories (
    super_category_id    INT          PRIMARY KEY AUTO_INCREMENT,
    super_category_name  VARCHAR(100) NOT NULL,
    super_category_image VARCHAR(500),
    display_order        INT          DEFAULT 0,
    is_active             TINYINT(1)   DEFAULT 1,
    created_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS super_category_links (
    link_id            INT       PRIMARY KEY AUTO_INCREMENT,
    super_category_id  INT       NOT NULL,
    category_id        INT       NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (super_category_id) REFERENCES super_categories(super_category_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id)       REFERENCES categories(category_id)             ON DELETE CASCADE,
    UNIQUE KEY uniq_link (super_category_id, category_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 5) add_location_coordinates.sql
-- Shop map picker (web) + customer GPS location (mobile app).
-- =============================================================================

ALTER TABLE shops
    ADD COLUMN latitude  DECIMAL(10,7) NULL AFTER shop_pincode,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;

ALTER TABLE customer_profiles
    ADD COLUMN latitude            DECIMAL(10,7) NULL,
    ADD COLUMN longitude           DECIMAL(10,7) NULL,
    ADD COLUMN location_updated_at TIMESTAMP NULL;


-- =============================================================================
-- 6) add_distance_delivery.sql
-- Delivery address coordinates + admin-configurable distance-based fees.
-- =============================================================================

ALTER TABLE addresses
    ADD COLUMN latitude  DECIMAL(10,7) NULL,
    ADD COLUMN longitude DECIMAL(10,7) NULL;

INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_type, description) VALUES
    ('delivery_fee_under_20km', '29', 'number', 'Delivery fee when shop-to-customer distance is under 20km'),
    ('delivery_fee_above_20km', '49', 'number', 'Delivery fee when shop-to-customer distance is 20km or more');

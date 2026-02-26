-- =============================================================================
-- TrenCart Rating System Migration
-- Run this once in phpMyAdmin after the main schema is already loaded.
-- =============================================================================

-- Step 1: Add unique constraint to prevent duplicate reviews
-- (one review per product per order per customer)
ALTER TABLE product_reviews
    ADD UNIQUE KEY unique_product_order_user (product_id, user_id, order_id);

-- Step 2: Drop old trigger and recreate it to also update shop rating
DROP TRIGGER IF EXISTS after_review_insert;

DELIMITER //

CREATE TRIGGER after_review_insert
AFTER INSERT ON product_reviews
FOR EACH ROW
BEGIN
    DECLARE v_shop_id INT;

    -- Get the shop that owns this product
    SELECT shop_id INTO v_shop_id
    FROM products
    WHERE product_id = NEW.product_id
    LIMIT 1;

    -- Update product's own rating average
    UPDATE products
    SET rating_average = COALESCE(
            (SELECT AVG(rating) FROM product_reviews
             WHERE product_id = NEW.product_id AND is_approved = 1), 0),
        total_ratings  = (SELECT COUNT(*) FROM product_reviews
                          WHERE product_id = NEW.product_id AND is_approved = 1)
    WHERE product_id = NEW.product_id;

    -- Update shop rating average (average across all its products' approved reviews)
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

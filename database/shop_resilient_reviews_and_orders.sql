-- =============================================================================
-- Make reviews and order history survive a product being deleted
-- Run this once in phpMyAdmin (or MySQL CLI) after rating_migration.sql.
--
-- Today, shops hard-delete sold-out products (DELETE FROM products ...).
-- Both order_items.product_id AND product_reviews.product_id are
-- ON DELETE CASCADE, so deleting one product silently deletes:
--   - every past order's LINE ITEM for that product (corrupting order
--     history and the shop payout totals calculated from it), and
--   - every review ever written for that product.
-- order_items already stores its own product_name snapshot, so once the
-- cascade is fixed it already displays fine with no live product. This
-- migration gives product_reviews the same resilience: a shop_id and a
-- product_name snapshot captured at submission time, independent of
-- whatever later happens to the product row.
-- =============================================================================

-- Step 1: order_items — stop a product delete from deleting order history
ALTER TABLE order_items MODIFY COLUMN product_id INT NULL;
ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_3;
ALTER TABLE order_items
    ADD CONSTRAINT order_items_ibfk_3
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL;

-- Step 2: product_reviews — add shop_id + a product_name snapshot
ALTER TABLE product_reviews
    ADD COLUMN shop_id INT NULL AFTER product_id,
    ADD COLUMN product_name VARCHAR(255) NULL AFTER shop_id;

-- Backfill existing reviews from order_items, which already carries both
-- shop_id and product_name independent of the live products table
UPDATE product_reviews pr
INNER JOIN order_items oi
        ON oi.order_id = pr.order_id AND oi.product_id = pr.product_id
SET pr.shop_id = oi.shop_id,
    pr.product_name = oi.product_name
WHERE pr.shop_id IS NULL;

-- Best-effort fallback for any review with no matching order_item row
UPDATE product_reviews pr
INNER JOIN products p ON p.product_id = pr.product_id
SET pr.shop_id = p.shop_id,
    pr.product_name = COALESCE(pr.product_name, p.product_name)
WHERE pr.shop_id IS NULL;

ALTER TABLE product_reviews MODIFY COLUMN shop_id INT NOT NULL;
ALTER TABLE product_reviews MODIFY COLUMN product_id INT NULL;

ALTER TABLE product_reviews DROP FOREIGN KEY product_reviews_ibfk_1;
ALTER TABLE product_reviews
    ADD CONSTRAINT product_reviews_ibfk_1
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL;
ALTER TABLE product_reviews
    ADD CONSTRAINT product_reviews_ibfk_shop
    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE;

-- Step 3: shop rating trigger — use product_reviews.shop_id directly instead
-- of joining through products, so a shop's rating stays correct even after
-- a reviewed product is later deleted. Product-level rating is unaffected
-- (it only ever matters while the product still exists).
DROP TRIGGER IF EXISTS after_review_insert;

DELIMITER //

CREATE TRIGGER after_review_insert
AFTER INSERT ON product_reviews
FOR EACH ROW
BEGIN
    IF NEW.product_id IS NOT NULL THEN
        UPDATE products
        SET rating_average = COALESCE(
                (SELECT AVG(rating) FROM product_reviews
                 WHERE product_id = NEW.product_id AND is_approved = 1), 0),
            total_ratings  = (SELECT COUNT(*) FROM product_reviews
                              WHERE product_id = NEW.product_id AND is_approved = 1)
        WHERE product_id = NEW.product_id;
    END IF;

    UPDATE shops
    SET rating_average = COALESCE(
            (SELECT AVG(rating) FROM product_reviews
             WHERE shop_id = NEW.shop_id AND is_approved = 1), 0),
        total_ratings  = (SELECT COUNT(*) FROM product_reviews
                          WHERE shop_id = NEW.shop_id AND is_approved = 1)
    WHERE shop_id = NEW.shop_id;
END//

DELIMITER ;

-- Verify
SHOW COLUMNS FROM product_reviews;
SELECT COUNT(*) AS reviews_missing_shop_id FROM product_reviews WHERE shop_id IS NULL;

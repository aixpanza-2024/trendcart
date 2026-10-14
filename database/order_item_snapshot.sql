-- =============================================================================
-- Order line snapshot: SKU + image
-- Run this once in phpMyAdmin, after shop_resilient_reviews_and_orders.sql.
--
-- order_items already keeps its own product_name, size and color, so a past
-- order still reads correctly after the product is deleted (product_id goes
-- NULL). The SKU and the photo were still read live from the product, so
-- they vanished on delete — and with reusable QR/SKU labels, a live SKU
-- would even show the NEW product's code on an old order. This stores both
-- on the order line at order time.
-- =============================================================================

-- Step 1: image column (product_code already exists on order_items, unused)
SET @col_img = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'order_items'
              AND COLUMN_NAME  = 'product_image'
        ),
        'SELECT "order_items.product_image already exists"',
        'ALTER TABLE order_items ADD COLUMN product_image VARCHAR(500) NULL AFTER product_code'
    )
);
PREPARE _s FROM @col_img; EXECUTE _s; DEALLOCATE PREPARE _s;

-- Step 2: backfill existing order lines whose product still exists.
-- Best effort: this is the product's CURRENT SKU/photo, the closest record
-- available for orders placed before this snapshot existed.
UPDATE order_items oi
INNER JOIN products p ON p.product_id = oi.product_id
SET oi.product_code = COALESCE(oi.product_code, p.product_code)
WHERE oi.product_code IS NULL;

UPDATE order_items oi
SET oi.product_image = (
    SELECT pi.image_url FROM product_images pi
    WHERE pi.product_id = oi.product_id
    ORDER BY pi.is_primary DESC, pi.display_order ASC
    LIMIT 1
)
WHERE oi.product_image IS NULL AND oi.product_id IS NOT NULL;

-- Verify
SELECT COUNT(*)                     AS order_lines,
       SUM(product_code  IS NOT NULL) AS with_sku,
       SUM(product_image IS NOT NULL) AS with_image,
       SUM(product_id    IS NULL)     AS product_deleted
FROM order_items;

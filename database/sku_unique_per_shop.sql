-- =============================================================================
-- SKU (product_code) unique per shop instead of across the whole platform
-- Run this once in phpMyAdmin. Safe to run again.
--
-- The office prints reusable QR labels (SM0001 ...) per shop. Two shops may
-- use the same SKU, and once a product is deleted its SKU is free again —
-- only two EXISTING products of the same shop may not share one. Order lines
-- keep their own SKU copy (order_item_snapshot.sql), so reuse never changes
-- what an old order shows.
-- =============================================================================

-- Step 1: blank SKUs become NULL (NULLs never clash in a unique key; '' would)
UPDATE products SET product_code = NULL WHERE TRIM(product_code) = '';

-- Step 2: drop the platform-wide unique index on product_code alone
-- (its name differs between installs, so look it up)
SET @old_idx = (
    SELECT s.INDEX_NAME
    FROM information_schema.STATISTICS s
    WHERE s.TABLE_SCHEMA = DATABASE()
      AND s.TABLE_NAME   = 'products'
      AND s.NON_UNIQUE   = 0
      AND s.COLUMN_NAME  = 'product_code'
      AND s.INDEX_NAME  <> 'uq_shop_product_code'
      AND (SELECT COUNT(*) FROM information_schema.STATISTICS s2
           WHERE s2.TABLE_SCHEMA = s.TABLE_SCHEMA
             AND s2.TABLE_NAME   = s.TABLE_NAME
             AND s2.INDEX_NAME   = s.INDEX_NAME) = 1
    LIMIT 1
);
SET @sql = IF(@old_idx IS NULL,
    'SELECT "no platform-wide product_code index to drop"',
    CONCAT('ALTER TABLE products DROP INDEX `', @old_idx, '`'));
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- Step 3: unique per shop
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME   = 'products'
             AND INDEX_NAME   = 'uq_shop_product_code'),
    'SELECT "uq_shop_product_code already exists"',
    'ALTER TABLE products ADD UNIQUE KEY uq_shop_product_code (shop_id, product_code)');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- Verify: expect one row, uq_shop_product_code on (shop_id, product_code)
SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
  AND INDEX_NAME IN (SELECT INDEX_NAME FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
                       AND COLUMN_NAME = 'product_code')
GROUP BY INDEX_NAME, NON_UNIQUE;

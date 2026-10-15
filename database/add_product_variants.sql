-- =============================================================================
-- Product variants: one row per colour + size, each with its own stock and SKU
-- Run once in phpMyAdmin, AFTER sku_unique_per_shop.sql. Safe to run again.
--
-- product_variants becomes the source of truth for stock. product_sizes,
-- product_colors and products.stock_quantity are kept as summaries that
-- api/utils/ProductVariants.php rebuilds after every change, so the pages
-- and app endpoints that read them keep working unchanged.
--
-- Existing products are converted below:
--   * with sizes    -> one variant per size, colour '' (stock per size kept)
--   * without sizes -> one variant ('' / '') holding the product's stock
-- Colours are NOT split automatically: the old system never stored stock per
-- colour, so it can't be guessed. Those products keep working exactly as
-- before (customer picks a colour from the list, stock is per size) until
-- the shop splits the stock per colour in Edit Product. The last query
-- lists them.
-- =============================================================================

CREATE TABLE IF NOT EXISTS product_variants (
    variant_id       INT           PRIMARY KEY AUTO_INCREMENT,
    product_id       INT           NOT NULL,
    shop_id          INT           NOT NULL,                 -- copy of products.shop_id, for per-shop SKU uniqueness
    color_name       VARCHAR(50)   NOT NULL DEFAULT '',      -- '' = no colour / not split by colour
    size_label       VARCHAR(30)   NOT NULL DEFAULT '',      -- '' = no size
    sku              VARCHAR(50)   NULL,                     -- QR/SKU label for this exact item
    stock_quantity   INT           NOT NULL DEFAULT 0,
    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0.00,    -- added to products.price
    display_order    INT           NOT NULL DEFAULT 0,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_variant_combo (product_id, color_name, size_label),
    UNIQUE KEY uq_variant_shop_sku (shop_id, sku),
    INDEX idx_variant_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (shop_id)    REFERENCES shops(shop_id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order lines remember the exact variant (for restocking on cancel)
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'variant_id'),
    'SELECT "order_items.variant_id already exists"',
    'ALTER TABLE order_items ADD COLUMN variant_id INT NULL AFTER product_id,
         ADD INDEX idx_oi_variant (variant_id),
         ADD CONSTRAINT fk_oi_variant FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id) ON DELETE SET NULL');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- Convert: products that have sizes -> one variant per size
INSERT INTO product_variants (product_id, shop_id, color_name, size_label, stock_quantity, price_adjustment, display_order)
SELECT ps.product_id, p.shop_id, '', ps.size_label, ps.stock_quantity, ps.price_adjustment, ps.display_order
FROM product_sizes ps
INNER JOIN products p ON p.product_id = ps.product_id
WHERE NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = ps.product_id);

-- Convert: products without sizes -> one variant with the product's stock
INSERT INTO product_variants (product_id, shop_id, color_name, size_label, stock_quantity, price_adjustment, display_order)
SELECT p.product_id, p.shop_id, '', '', GREATEST(0, COALESCE(p.stock_quantity, 0)), 0, 0
FROM products p
WHERE NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.product_id);

-- Verify 1: every product has at least one variant, and total stock matches
SELECT
    (SELECT COUNT(*) FROM products)                                        AS products,
    (SELECT COUNT(DISTINCT product_id) FROM product_variants)              AS products_with_variants,
    (SELECT COALESCE(SUM(stock_quantity), 0) FROM products)                AS stock_before,
    (SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants)        AS stock_in_variants;

-- Verify 2: products whose stock still needs splitting per colour (give this list to the shops)
SELECT p.product_id, s.shop_name, p.product_name,
       GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.display_order SEPARATOR ', ') AS colours
FROM products p
INNER JOIN shops s          ON s.shop_id = p.shop_id
INNER JOIN product_colors pc ON pc.product_id = p.product_id
WHERE NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.product_id AND v.color_name <> '')
GROUP BY p.product_id, s.shop_name, p.product_name
ORDER BY s.shop_name, p.product_name;

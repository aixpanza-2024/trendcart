-- ============================================================
-- TrenCart: Add Size Variants Support
-- MySQL 5.7+ compatible (no ADD COLUMN IF NOT EXISTS).
-- Run this once in phpMyAdmin against the trencart database.
-- It is safe to re-run: all operations check existence first.
-- ============================================================

-- 1. Add `size` summary column to products if missing
--    (comma-joined label summary, e.g. "S,M,XL")
SET @col_size = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'products'
              AND COLUMN_NAME  = 'size'
        ),
        'SELECT "products.size already exists"',
        'ALTER TABLE products ADD COLUMN size VARCHAR(50) NULL AFTER product_description'
    )
);
PREPARE _s FROM @col_size; EXECUTE _s; DEALLOCATE PREPARE _s;


-- 2. Size variants table (one row per size per product)
CREATE TABLE IF NOT EXISTS product_sizes (
    size_id          INT           PRIMARY KEY AUTO_INCREMENT,
    product_id       INT           NOT NULL,
    size_label       VARCHAR(30)   NOT NULL,          -- e.g. 'S', 'XL', 'Free Size', '38'
    stock_quantity   INT           NOT NULL DEFAULT 0,
    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- added to base price; usually 0
    display_order    INT           NOT NULL DEFAULT 0,
    UNIQUE KEY uq_product_size (product_id, size_label),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 3. Add `selected_size` to order_items if missing
SET @col_sel = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'order_items'
              AND COLUMN_NAME  = 'selected_size'
        ),
        'SELECT "order_items.selected_size already exists"',
        'ALTER TABLE order_items ADD COLUMN selected_size VARCHAR(30) NULL AFTER product_name'
    )
);
PREPARE _s FROM @col_sel; EXECUTE _s; DEALLOCATE PREPARE _s;

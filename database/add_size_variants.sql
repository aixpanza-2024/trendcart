-- ============================================================
-- TrenCart: Add Size Variants Support
-- Run this migration once against the trencart database
-- ============================================================

-- 1. Size variants per product (S, M, L, XL, XXL, etc.)
CREATE TABLE IF NOT EXISTS product_sizes (
    size_id          INT           PRIMARY KEY AUTO_INCREMENT,
    product_id       INT           NOT NULL,
    size_label       VARCHAR(30)   NOT NULL,          -- e.g. 'S', 'XL', 'Free Size', '38'
    stock_quantity   INT           NOT NULL DEFAULT 0,
    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- added to base price; usually 0
    display_order    INT           NOT NULL DEFAULT 0,
    UNIQUE KEY uq_product_size (product_id, size_label),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- 2. Record selected size on each order line
ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS selected_size VARCHAR(30) NULL AFTER product_name;

-- Migration: Add product color variants and selected_color to order_items
-- Run once on production database

-- 1. product_colors table
CREATE TABLE IF NOT EXISTS product_colors (
    color_id       INT PRIMARY KEY AUTO_INCREMENT,
    product_id     INT NOT NULL,
    color_name     VARCHAR(50) NOT NULL,
    display_order  INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
);

-- 2. Add selected_color to order_items (run only if column doesn't exist)
ALTER TABLE order_items
    ADD COLUMN selected_color VARCHAR(50) DEFAULT NULL AFTER selected_size;

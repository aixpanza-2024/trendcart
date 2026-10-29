-- =============================================================================
-- Product views per day — feeds the homepage "Trending Now" row
-- Run this once in phpMyAdmin. Safe to run again.
--
-- Most items are one piece, so sales alone can't tell what's popular.
-- Trending ranks products by the last 7 days of interest instead:
-- views (this table) + wishlist saves + units ordered. See
-- api/utils/TrendingProducts.php. Until this is run, the site still works
-- and Trending just leaves views out.
-- =============================================================================

CREATE TABLE IF NOT EXISTS product_views_daily (
    product_id INT(11) NOT NULL,
    view_date  DATE    NOT NULL,
    views      INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id, view_date),
    KEY idx_view_date (view_date),
    CONSTRAINT fk_pvd_product FOREIGN KEY (product_id) REFERENCES products (product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: Super categories (Men / Women / Kids / Cosmetics ...)
-- Run once in phpMyAdmin
-- Purely additive — does not touch categories, products, or any existing table

CREATE TABLE IF NOT EXISTS super_categories (
    super_category_id    INT          PRIMARY KEY AUTO_INCREMENT,
    super_category_name  VARCHAR(100) NOT NULL,
    super_category_image VARCHAR(500),
    display_order        INT          DEFAULT 0,
    is_active             TINYINT(1)   DEFAULT 1,
    created_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links a super category to existing top-level (parent) categories.
-- Many-to-many: one parent category (e.g. "Jeans") can belong to more than
-- one super category (e.g. both "Men" and "Women") at the same time.
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

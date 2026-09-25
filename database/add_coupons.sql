-- Coupons Module
-- Run this ONCE via phpMyAdmin or MySQL CLI. Safe for existing data: only adds new
-- tables and two new nullable/defaulted columns on orders.

CREATE TABLE IF NOT EXISTS coupons (
    coupon_id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255) NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,          -- percent (0-100) when type=percentage, else a flat rupee amount
    max_discount_amount DECIMAL(10,2) NULL,         -- caps a percentage discount; NULL = uncapped
    min_order_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    usage_limit_total INT NULL,                     -- NULL = unlimited redemptions overall
    usage_limit_per_user INT NOT NULL DEFAULT 1,     -- always capped per customer (anti-abuse); default 1
    times_used INT NOT NULL DEFAULT 0,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_usage (
    usage_id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(coupon_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    INDEX idx_coupon_user (coupon_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- orders.discount_amount already exists in the base schema (unused until now) — only
-- coupon_code is new.
ALTER TABLE orders
    ADD COLUMN coupon_code VARCHAR(50) NULL AFTER handling_fee;

-- Verify
SHOW COLUMNS FROM orders LIKE 'coupon_code';
SHOW COLUMNS FROM orders LIKE 'discount_amount';
DESCRIBE coupons;
DESCRIBE coupon_usage;

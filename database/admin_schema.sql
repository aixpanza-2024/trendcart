-- =====================================================
-- TrenCart - Admin Panel Extended Schema
-- Payment Tracking & Admin Operations
-- =====================================================
-- Run after schema.sql and products_orders_schema.sql

USE trencart_db;

-- =====================================================
-- SHOP PAYMENTS TABLE
-- Track weekly payments to shops
-- =====================================================

CREATE TABLE IF NOT EXISTS shop_payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NOT NULL,

    -- Payment Period
    period_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Amounts
    total_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    commission_rate DECIMAL(5,2) DEFAULT 10.00, -- Platform commission %
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    -- Payment Status
    payment_status ENUM('unpaid', 'paid', 'partially_paid') DEFAULT 'unpaid',
    paid_amount DECIMAL(12,2) DEFAULT 0.00,
    payment_method VARCHAR(50),
    transaction_reference VARCHAR(100),
    paid_at TIMESTAMP NULL,
    paid_by INT, -- admin user_id who marked as paid

    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE,
    FOREIGN KEY (paid_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_shop (shop_id),
    INDEX idx_period (period_start, period_end),
    INDEX idx_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADMIN ACTIVITY LOG
-- Track all admin actions
-- =====================================================

CREATE TABLE IF NOT EXISTS admin_activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50), -- 'order', 'shop', 'customer', 'payment'
    entity_id INT,
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action_type),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PLATFORM SETTINGS TABLE
-- Configurable admin settings
-- =====================================================

CREATE TABLE IF NOT EXISTS platform_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description VARCHAR(255),
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO platform_settings (setting_key, setting_value, setting_type, description) VALUES
('commission_rate', '10', 'number', 'Default platform commission rate (%)'),
('payment_cycle', 'weekly', 'text', 'Payment settlement cycle'),
('min_payout', '500', 'number', 'Minimum payout amount in INR'),
('order_auto_confirm_hours', '24', 'number', 'Hours before auto-confirming orders'),
('max_return_days', '7', 'number', 'Maximum days for return request'),
('cod_enabled', '1', 'boolean', 'Cash on delivery enabled'),
('platform_name', 'TrenCart', 'text', 'Platform name');

-- =====================================================
-- VIEWS FOR ADMIN DASHBOARD
-- =====================================================

-- Revenue overview by shop
CREATE OR REPLACE VIEW admin_shop_revenue AS
SELECT
    s.shop_id,
    s.shop_name,
    s.shop_status,
    s.user_id,
    u.email as shop_email,
    u.phone as shop_phone,
    s.total_products,
    s.total_orders,
    s.total_sales,
    COALESCE(
        (SELECT SUM(oi.subtotal)
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.order_id
         WHERE oi.shop_id = s.shop_id
         AND o.order_date >= CURDATE()), 0
    ) as today_sales,
    COALESCE(
        (SELECT SUM(oi.subtotal)
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.order_id
         WHERE oi.shop_id = s.shop_id
         AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)), 0
    ) as weekly_sales,
    COALESCE(
        (SELECT SUM(oi.subtotal)
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.order_id
         WHERE oi.shop_id = s.shop_id
         AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0
    ) as monthly_sales,
    COALESCE(
        (SELECT SUM(paid_amount)
         FROM shop_payments
         WHERE shop_id = s.shop_id AND payment_status = 'paid'), 0
    ) as total_paid,
    COALESCE(
        (SELECT SUM(payable_amount - paid_amount)
         FROM shop_payments
         WHERE shop_id = s.shop_id AND payment_status != 'paid'), 0
    ) as total_pending
FROM shops s
INNER JOIN users u ON s.user_id = u.user_id;

-- Daily revenue summary
CREATE OR REPLACE VIEW admin_daily_revenue AS
SELECT
    DATE(o.order_date) as order_day,
    COUNT(DISTINCT o.order_id) as total_orders,
    SUM(o.total_amount) as total_revenue,
    SUM(CASE WHEN o.order_status = 'delivered' THEN o.total_amount ELSE 0 END) as delivered_revenue,
    SUM(CASE WHEN o.order_status = 'cancelled' THEN o.total_amount ELSE 0 END) as cancelled_revenue,
    COUNT(DISTINCT o.customer_id) as unique_customers
FROM orders o
GROUP BY DATE(o.order_date)
ORDER BY order_day DESC;

-- Order status summary
CREATE OR REPLACE VIEW admin_order_summary AS
SELECT
    o.order_id,
    o.order_number,
    o.order_date,
    o.total_amount,
    o.order_status,
    o.payment_status,
    o.payment_method,
    u.full_name as customer_name,
    u.email as customer_email,
    u.phone as customer_phone,
    o.shipping_city,
    COUNT(oi.order_item_id) as item_count,
    GROUP_CONCAT(DISTINCT s.shop_name SEPARATOR ', ') as shop_names
FROM orders o
INNER JOIN users u ON o.customer_id = u.user_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
LEFT JOIN shops s ON oi.shop_id = s.shop_id
GROUP BY o.order_id;

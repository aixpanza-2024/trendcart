-- =====================================================
-- TRENCART - COMPLETE DATABASE SCHEMA (ALL-IN-ONE)
-- Combines: schema.sql + products_orders_schema.sql + admin_schema.sql
-- Safe to run on existing database (uses IF NOT EXISTS)
-- =====================================================

-- Database Creation
CREATE DATABASE IF NOT EXISTS trencart_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trencart_db;


-- =============================================================================
-- SECTION 1: CORE TABLES (Users, Auth, Profiles)
-- =============================================================================

-- USERS TABLE (Multi-Role Support)
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    user_type ENUM('customer', 'shop', 'admin', 'delivery_boy') NOT NULL DEFAULT 'customer',
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP TABLE (For Email Verification)
CREATE TABLE IF NOT EXISTS otp_verification (
    otp_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('registration', 'login', 'password_reset') NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_otp_code (otp_code),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CUSTOMER PROFILES
CREATE TABLE IF NOT EXISTS customer_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    profile_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SHOP PROFILES
CREATE TABLE IF NOT EXISTS shop_profiles (
    shop_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    shop_name VARCHAR(255) NOT NULL,
    shop_slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    shop_image VARCHAR(255) NULL,
    business_registration VARCHAR(100) NULL,
    gst_number VARCHAR(20) NULL,
    rating DECIMAL(2,1) DEFAULT 0.0,
    total_reviews INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_shop_slug (shop_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADMIN PROFILES
CREATE TABLE IF NOT EXISTS admin_profiles (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    admin_level ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    permissions JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DELIVERY BOY PROFILES (Future)
CREATE TABLE IF NOT EXISTS delivery_profiles (
    delivery_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    vehicle_type ENUM('bike', 'scooter', 'bicycle', 'car') NULL,
    vehicle_number VARCHAR(20) NULL,
    license_number VARCHAR(50) NULL,
    is_available BOOLEAN DEFAULT TRUE,
    rating DECIMAL(2,1) DEFAULT 0.0,
    total_deliveries INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADDRESSES TABLE
CREATE TABLE IF NOT EXISTS addresses (
    address_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    address_type ENUM('home', 'work', 'other') DEFAULT 'home',
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SESSIONS TABLE
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOGIN ATTEMPTS (Security)
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_successful BOOLEAN DEFAULT FALSE,
    INDEX idx_email_ip (email, ip_address),
    INDEX idx_attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SECTION 2: E-COMMERCE TABLES (Shops, Products, Orders)
-- =============================================================================

-- SHOPS TABLE (Detailed shop information)
CREATE TABLE IF NOT EXISTS shops (
    shop_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    shop_name VARCHAR(255) NOT NULL,
    shop_description TEXT,
    shop_logo VARCHAR(500),
    shop_banner VARCHAR(500),
    shop_status ENUM('open', 'closed', 'suspended') DEFAULT 'open',
    shop_address TEXT,
    shop_city VARCHAR(100),
    shop_state VARCHAR(100),
    shop_pincode VARCHAR(10),
    shop_phone VARCHAR(15),
    shop_email VARCHAR(100),
    business_hours JSON,
    rating_average DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    total_products INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    total_sales DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_shop_status (shop_status),
    INDEX idx_shop_city (shop_city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    parent_category_id INT DEFAULT NULL,
    category_description TEXT,
    category_image VARCHAR(500),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    INDEX idx_parent_category (parent_category_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRODUCTS TABLE
CREATE TABLE IF NOT EXISTS products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NOT NULL,
    category_id INT,
    product_name VARCHAR(255) NOT NULL,
    product_description TEXT,
    product_code VARCHAR(50) UNIQUE,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    color VARCHAR(50),
    size VARCHAR(50),
    material VARCHAR(100),
    fabric_type VARCHAR(100),
    pattern VARCHAR(100),
    length DECIMAL(8,2),
    width DECIMAL(8,2),
    weight DECIMAL(8,2),
    product_status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    is_featured TINYINT(1) DEFAULT 0,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(500),
    views_count INT DEFAULT 0,
    orders_count INT DEFAULT 0,
    rating_average DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    INDEX idx_shop (shop_id),
    INDEX idx_category (category_id),
    INDEX idx_status (product_status),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price),
    FULLTEXT idx_search (product_name, product_description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRODUCT IMAGES TABLE
CREATE TABLE IF NOT EXISTS product_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDERS TABLE
CREATE TABLE IF NOT EXISTS orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cod', 'online', 'wallet') DEFAULT 'cod',
    shipping_name VARCHAR(100) NOT NULL,
    shipping_email VARCHAR(100),
    shipping_phone VARCHAR(15) NOT NULL,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_state VARCHAR(100) NOT NULL,
    shipping_pincode VARCHAR(10) NOT NULL,
    tracking_number VARCHAR(100),
    delivery_notes TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_customer (customer_id),
    INDEX idx_status (order_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_order_date (order_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDER ITEMS TABLE
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    shop_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_code VARCHAR(50),
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL,
    product_snapshot JSON,
    item_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    confirmed_by_shop_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_shop (shop_id),
    INDEX idx_product (product_id),
    INDEX idx_item_status (item_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDER STATUS HISTORY TABLE
CREATE TABLE IF NOT EXISTS order_status_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    order_item_id INT,
    status_from VARCHAR(50),
    status_to VARCHAR(50) NOT NULL,
    changed_by INT,
    change_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_item (order_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SHOPPING CART TABLE
CREATE TABLE IF NOT EXISTS cart (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WISHLIST TABLE
CREATE TABLE IF NOT EXISTS wishlist (
    wishlist_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRODUCT REVIEWS TABLE
CREATE TABLE IF NOT EXISTS product_reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_title VARCHAR(255),
    review_text TEXT,
    is_verified_purchase TINYINT(1) DEFAULT 0,
    is_approved TINYINT(1) DEFAULT 0,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL,
    INDEX idx_product (product_id),
    INDEX idx_user (user_id),
    INDEX idx_approved (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT,
    related_type VARCHAR(50),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SECTION 3: ADMIN TABLES (Payments, Activity Log, Settings)
-- =============================================================================

-- SHOP PAYMENTS TABLE (Weekly payment tracking)
CREATE TABLE IF NOT EXISTS shop_payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('unpaid', 'paid', 'partially_paid') DEFAULT 'unpaid',
    paid_amount DECIMAL(12,2) DEFAULT 0.00,
    payment_method VARCHAR(50),
    transaction_reference VARCHAR(100),
    paid_at TIMESTAMP NULL,
    paid_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE,
    FOREIGN KEY (paid_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_shop (shop_id),
    INDEX idx_period (period_start, period_end),
    INDEX idx_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADMIN ACTIVITY LOG
CREATE TABLE IF NOT EXISTS admin_activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
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

-- PLATFORM SETTINGS TABLE
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


-- =============================================================================
-- SECTION 4: DEFAULT DATA
-- =============================================================================

-- Default Admin User (only inserts if no admin exists)
INSERT INTO users (email, full_name, phone, user_type, is_verified, is_active)
SELECT 'admin@trencart.com', 'Super Admin', '9999999999', 'admin', TRUE, TRUE
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@trencart.com');

INSERT INTO admin_profiles (user_id, admin_level, permissions)
SELECT user_id, 'super_admin', '{"all": true}'
FROM users WHERE email = 'admin@trencart.com'
AND NOT EXISTS (SELECT 1 FROM admin_profiles ap INNER JOIN users u ON ap.user_id = u.user_id WHERE u.email = 'admin@trencart.com');

-- Default Categories (only inserts if categories table is empty)
INSERT INTO categories (category_name, category_description, display_order)
SELECT * FROM (
    SELECT 'Sarees' as n, 'Traditional Indian sarees in various fabrics' as d, 1 as o UNION ALL
    SELECT 'Dress Materials', 'Unstitched dress materials and fabrics', 2 UNION ALL
    SELECT 'Fabrics', 'Premium fabrics by meter', 3 UNION ALL
    SELECT 'Kurtis & Tops', 'Ready-made kurtis and tops', 4 UNION ALL
    SELECT 'Lehengas', 'Bridal and party lehengas', 5 UNION ALL
    SELECT 'Dupattas', 'Dupattas and stoles', 6
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM categories LIMIT 1);

-- Sub-categories (only if parent exists and subcategories don't)
INSERT INTO categories (category_name, parent_category_id, category_description, display_order)
SELECT * FROM (
    SELECT 'Silk Sarees' as n, (SELECT category_id FROM categories WHERE category_name='Sarees' LIMIT 1) as p, 'Pure silk sarees' as d, 1 as o UNION ALL
    SELECT 'Cotton Sarees', (SELECT category_id FROM categories WHERE category_name='Sarees' LIMIT 1), 'Cotton and handloom sarees', 2 UNION ALL
    SELECT 'Georgette Sarees', (SELECT category_id FROM categories WHERE category_name='Sarees' LIMIT 1), 'Georgette and chiffon sarees', 3 UNION ALL
    SELECT 'Designer Sarees', (SELECT category_id FROM categories WHERE category_name='Sarees' LIMIT 1), 'Designer and party wear sarees', 4 UNION ALL
    SELECT 'Cotton Suits', (SELECT category_id FROM categories WHERE category_name='Dress Materials' LIMIT 1), 'Cotton dress materials', 1 UNION ALL
    SELECT 'Silk Suits', (SELECT category_id FROM categories WHERE category_name='Dress Materials' LIMIT 1), 'Silk dress materials', 2 UNION ALL
    SELECT 'Churidar Materials', (SELECT category_id FROM categories WHERE category_name='Dress Materials' LIMIT 1), 'Churidar dress materials', 3
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_category_id IS NOT NULL LIMIT 1);

-- Default Platform Settings (only if empty)
INSERT INTO platform_settings (setting_key, setting_value, setting_type, description)
SELECT * FROM (
    SELECT 'commission_rate' as k, '10' as v, 'number' as t, 'Default platform commission rate (%)' as d UNION ALL
    SELECT 'payment_cycle', 'weekly', 'text', 'Payment settlement cycle' UNION ALL
    SELECT 'min_payout', '500', 'number', 'Minimum payout amount in INR' UNION ALL
    SELECT 'order_auto_confirm_hours', '24', 'number', 'Hours before auto-confirming orders' UNION ALL
    SELECT 'max_return_days', '7', 'number', 'Maximum days for return request' UNION ALL
    SELECT 'cod_enabled', '1', 'boolean', 'Cash on delivery enabled' UNION ALL
    SELECT 'platform_name', 'TrenCart', 'text', 'Platform name'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM platform_settings LIMIT 1);


-- =============================================================================
-- SECTION 5: VIEWS
-- =============================================================================

-- Customer View
CREATE OR REPLACE VIEW customer_users AS
SELECT
    u.user_id, u.email, u.full_name, u.phone, u.is_verified, u.is_active,
    u.created_at, u.last_login,
    cp.date_of_birth, cp.gender, cp.profile_image
FROM users u
LEFT JOIN customer_profiles cp ON u.user_id = cp.user_id
WHERE u.user_type = 'customer';

-- Shop View
CREATE OR REPLACE VIEW shop_users AS
SELECT
    u.user_id, u.email, u.full_name, u.phone, u.is_verified, u.is_active,
    u.created_at, u.last_login,
    sp.shop_id, sp.shop_name, sp.shop_slug, sp.description, sp.shop_image,
    sp.rating, sp.total_reviews, sp.is_verified as shop_verified
FROM users u
LEFT JOIN shop_profiles sp ON u.user_id = sp.user_id
WHERE u.user_type = 'shop';

-- Shop Dashboard Statistics
CREATE OR REPLACE VIEW shop_dashboard_stats AS
SELECT
    s.shop_id, s.shop_name, s.shop_status,
    s.total_products, s.total_orders, s.total_sales,
    COUNT(DISTINCT p.product_id) as active_products,
    COUNT(DISTINCT oi.order_id) as pending_orders,
    COALESCE(SUM(CASE WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN oi.subtotal ELSE 0 END), 0) as sales_last_30_days
FROM shops s
LEFT JOIN products p ON s.shop_id = p.shop_id AND p.product_status = 'active'
LEFT JOIN order_items oi ON s.shop_id = oi.shop_id AND oi.item_status = 'pending'
LEFT JOIN orders o ON oi.order_id = o.order_id
GROUP BY s.shop_id;

-- Products with Shop Info
CREATE OR REPLACE VIEW products_with_shop AS
SELECT
    p.*, s.shop_name, s.shop_status, c.category_name,
    (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image
FROM products p
LEFT JOIN shops s ON p.shop_id = s.shop_id
LEFT JOIN categories c ON p.category_id = c.category_id;

-- Order Details View
CREATE OR REPLACE VIEW order_details AS
SELECT
    o.*, u.full_name as customer_name, u.email as customer_email,
    COUNT(oi.order_item_id) as total_items,
    GROUP_CONCAT(DISTINCT s.shop_name) as shop_names
FROM orders o
LEFT JOIN users u ON o.customer_id = u.user_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
LEFT JOIN shops s ON oi.shop_id = s.shop_id
GROUP BY o.order_id;

-- Admin Shop Revenue View
CREATE OR REPLACE VIEW admin_shop_revenue AS
SELECT
    s.shop_id, s.shop_name, s.shop_status, s.user_id,
    u.email as shop_email, u.phone as shop_phone,
    s.total_products, s.total_orders, s.total_sales,
    COALESCE((SELECT SUM(oi.subtotal) FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id WHERE oi.shop_id = s.shop_id AND o.order_date >= CURDATE()), 0) as today_sales,
    COALESCE((SELECT SUM(oi.subtotal) FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id WHERE oi.shop_id = s.shop_id AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)), 0) as weekly_sales,
    COALESCE((SELECT SUM(oi.subtotal) FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id WHERE oi.shop_id = s.shop_id AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0) as monthly_sales,
    COALESCE((SELECT SUM(paid_amount) FROM shop_payments WHERE shop_id = s.shop_id AND payment_status = 'paid'), 0) as total_paid,
    COALESCE((SELECT SUM(payable_amount - paid_amount) FROM shop_payments WHERE shop_id = s.shop_id AND payment_status != 'paid'), 0) as total_pending
FROM shops s
INNER JOIN users u ON s.user_id = u.user_id;

-- Daily Revenue Summary View
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

-- Order Status Summary View
CREATE OR REPLACE VIEW admin_order_summary AS
SELECT
    o.order_id, o.order_number, o.order_date, o.total_amount,
    o.order_status, o.payment_status, o.payment_method,
    u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
    o.shipping_city,
    COUNT(oi.order_item_id) as item_count,
    GROUP_CONCAT(DISTINCT s.shop_name SEPARATOR ', ') as shop_names
FROM orders o
INNER JOIN users u ON o.customer_id = u.user_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
LEFT JOIN shops s ON oi.shop_id = s.shop_id
GROUP BY o.order_id;


-- =============================================================================
-- SECTION 6: TRIGGERS
-- =============================================================================

-- Drop existing triggers first (safe for re-run)
DROP TRIGGER IF EXISTS after_product_insert;
DROP TRIGGER IF EXISTS after_product_delete;
DROP TRIGGER IF EXISTS after_review_insert;
DROP TRIGGER IF EXISTS after_order_status_update;

DELIMITER //

-- Auto-update shop product count on insert
CREATE TRIGGER after_product_insert
AFTER INSERT ON products
FOR EACH ROW
BEGIN
    UPDATE shops SET total_products = total_products + 1 WHERE shop_id = NEW.shop_id;
END//

-- Auto-update shop product count on delete
CREATE TRIGGER after_product_delete
AFTER DELETE ON products
FOR EACH ROW
BEGIN
    UPDATE shops SET total_products = total_products - 1 WHERE shop_id = OLD.shop_id;
END//

-- Auto-update product rating when review is added
CREATE TRIGGER after_review_insert
AFTER INSERT ON product_reviews
FOR EACH ROW
BEGIN
    UPDATE products
    SET rating_average = (SELECT AVG(rating) FROM product_reviews WHERE product_id = NEW.product_id AND is_approved = 1),
        total_ratings = (SELECT COUNT(*) FROM product_reviews WHERE product_id = NEW.product_id AND is_approved = 1)
    WHERE product_id = NEW.product_id;
END//

-- Auto-log order status changes
CREATE TRIGGER after_order_status_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.order_status != NEW.order_status THEN
        INSERT INTO order_status_history (order_id, status_from, status_to, change_note)
        VALUES (NEW.order_id, OLD.order_status, NEW.order_status, 'Order status changed');
    END IF;
END//

DELIMITER ;


-- =============================================================================
-- SECTION 7: STORED PROCEDURES
-- =============================================================================

DROP PROCEDURE IF EXISTS cleanup_expired_otps;
DROP PROCEDURE IF EXISTS cleanup_expired_sessions;

DELIMITER //

CREATE PROCEDURE cleanup_expired_otps()
BEGIN
    DELETE FROM otp_verification WHERE expires_at < NOW();
END //

CREATE PROCEDURE cleanup_expired_sessions()
BEGIN
    DELETE FROM user_sessions WHERE expires_at < NOW();
END //

DELIMITER ;


-- =============================================================================
-- DONE! TrenCart Complete Schema Loaded Successfully
-- =============================================================================
--
-- Tables Created: 22
-- ─────────────────────────────────────────────────
-- CORE:      users, otp_verification, customer_profiles, shop_profiles,
--            admin_profiles, delivery_profiles, addresses,
--            user_sessions, login_attempts
-- ECOMMERCE: shops, categories, products, product_images,
--            orders, order_items, order_status_history,
--            cart, wishlist, product_reviews, notifications
-- ADMIN:     shop_payments, admin_activity_log, platform_settings
-- ─────────────────────────────────────────────────
-- Views: 8 | Triggers: 4 | Procedures: 2
-- Default Admin: admin@trencart.com (login via OTP)
-- Default Categories: 6 main + 7 sub-categories
-- =====================================================

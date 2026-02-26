-- =====================================================
-- TrenCart - Products & Orders Database Schema
-- Extended Schema for E-commerce Operations
-- =====================================================

-- This schema extends the base schema (schema.sql)
-- Run schema.sql first, then run this file

USE trencart_db;

-- =====================================================
-- SHOPS TABLE
-- Detailed shop information (extends shop_profiles)
-- =====================================================

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
    business_hours JSON, -- {"monday": {"open": "09:00", "close": "18:00"}, ...}
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

-- =====================================================
-- CATEGORIES TABLE
-- Product categories and subcategories
-- =====================================================

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

-- =====================================================
-- PRODUCTS TABLE
-- Main product information
-- =====================================================

CREATE TABLE IF NOT EXISTS products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NOT NULL,
    category_id INT,
    product_name VARCHAR(255) NOT NULL,
    product_description TEXT,
    product_code VARCHAR(50) UNIQUE,

    -- Pricing
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,

    -- Stock Management
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,

    -- Product Details
    color VARCHAR(50),
    size VARCHAR(50),
    material VARCHAR(100),
    fabric_type VARCHAR(100),
    pattern VARCHAR(100),

    -- Measurements (for dress materials)
    length DECIMAL(8,2), -- in meters
    width DECIMAL(8,2),  -- in meters
    weight DECIMAL(8,2), -- in grams

    -- Status
    product_status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    is_featured TINYINT(1) DEFAULT 0,

    -- SEO
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(500),

    -- Statistics
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

-- =====================================================
-- PRODUCT IMAGES TABLE
-- Multiple images per product
-- =====================================================

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

-- =====================================================
-- ORDERS TABLE
-- Customer orders
-- =====================================================

CREATE TABLE IF NOT EXISTS orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,

    -- Order Amounts
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,

    -- Order Status
    order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cod', 'online', 'wallet') DEFAULT 'cod',

    -- Shipping Address
    shipping_name VARCHAR(100) NOT NULL,
    shipping_email VARCHAR(100),
    shipping_phone VARCHAR(15) NOT NULL,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_state VARCHAR(100) NOT NULL,
    shipping_pincode VARCHAR(10) NOT NULL,

    -- Tracking
    tracking_number VARCHAR(100),
    delivery_notes TEXT,

    -- Timestamps
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

-- =====================================================
-- ORDER ITEMS TABLE
-- Products in each order
-- =====================================================

CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    shop_id INT NOT NULL,
    product_id INT NOT NULL,

    product_name VARCHAR(255) NOT NULL,
    product_code VARCHAR(50),

    -- Item Details
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL,

    -- Product Snapshot (at time of order)
    product_snapshot JSON, -- Store product details as they were when ordered

    -- Item Status (per shop)
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

-- =====================================================
-- ORDER STATUS HISTORY TABLE
-- Track all order status changes
-- =====================================================

CREATE TABLE IF NOT EXISTS order_status_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    order_item_id INT,
    status_from VARCHAR(50),
    status_to VARCHAR(50) NOT NULL,
    changed_by INT, -- user_id who made the change
    change_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_item (order_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SHOPPING CART TABLE
-- Persistent cart (replaces localStorage)
-- =====================================================

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

-- =====================================================
-- WISHLIST TABLE
-- User wishlist/favorites
-- =====================================================

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

-- =====================================================
-- PRODUCT REVIEWS TABLE
-- Customer product reviews and ratings
-- =====================================================

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

-- =====================================================
-- NOTIFICATIONS TABLE
-- User notifications
-- =====================================================

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT, -- Can be order_id, product_id, etc.
    related_type VARCHAR(50), -- 'order', 'product', 'shop', etc.
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT CATEGORIES
-- =====================================================

INSERT INTO categories (category_name, category_description, display_order) VALUES
('Sarees', 'Traditional Indian sarees in various fabrics', 1),
('Dress Materials', 'Unstitched dress materials and fabrics', 2),
('Fabrics', 'Premium fabrics by meter', 3),
('Kurtis & Tops', 'Ready-made kurtis and tops', 4),
('Lehengas', 'Bridal and party lehengas', 5),
('Dupattas', 'Dupattas and stoles', 6);

-- Sub-categories for Sarees
INSERT INTO categories (category_name, parent_category_id, category_description, display_order) VALUES
('Silk Sarees', 1, 'Pure silk sarees', 1),
('Cotton Sarees', 1, 'Cotton and handloom sarees', 2),
('Georgette Sarees', 1, 'Georgette and chiffon sarees', 3),
('Designer Sarees', 1, 'Designer and party wear sarees', 4);

-- Sub-categories for Dress Materials
INSERT INTO categories (category_name, parent_category_id, category_description, display_order) VALUES
('Cotton Suits', 2, 'Cotton dress materials', 1),
('Silk Suits', 2, 'Silk dress materials', 2),
('Churidar Materials', 2, 'Churidar dress materials', 3);

-- =====================================================
-- TRIGGERS
-- =====================================================

-- Update shop total_products when product is added/removed
DELIMITER //
CREATE TRIGGER after_product_insert
AFTER INSERT ON products
FOR EACH ROW
BEGIN
    UPDATE shops
    SET total_products = total_products + 1
    WHERE shop_id = NEW.shop_id;
END//

CREATE TRIGGER after_product_delete
AFTER DELETE ON products
FOR EACH ROW
BEGIN
    UPDATE shops
    SET total_products = total_products - 1
    WHERE shop_id = OLD.shop_id;
END//
DELIMITER ;

-- Update product rating when review is added
DELIMITER //
CREATE TRIGGER after_review_insert
AFTER INSERT ON product_reviews
FOR EACH ROW
BEGIN
    UPDATE products
    SET rating_average = (
        SELECT AVG(rating)
        FROM product_reviews
        WHERE product_id = NEW.product_id AND is_approved = 1
    ),
    total_ratings = (
        SELECT COUNT(*)
        FROM product_reviews
        WHERE product_id = NEW.product_id AND is_approved = 1
    )
    WHERE product_id = NEW.product_id;
END//
DELIMITER ;

-- Log order status changes
DELIMITER //
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

-- =====================================================
-- VIEWS
-- =====================================================

-- Shop Dashboard Statistics
CREATE OR REPLACE VIEW shop_dashboard_stats AS
SELECT
    s.shop_id,
    s.shop_name,
    s.shop_status,
    s.total_products,
    s.total_orders,
    s.total_sales,
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
    p.*,
    s.shop_name,
    s.shop_status,
    c.category_name,
    (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image
FROM products p
LEFT JOIN shops s ON p.shop_id = s.shop_id
LEFT JOIN categories c ON p.category_id = c.category_id;

-- Order Details View
CREATE OR REPLACE VIEW order_details AS
SELECT
    o.*,
    u.full_name as customer_name,
    u.email as customer_email,
    COUNT(oi.order_item_id) as total_items,
    GROUP_CONCAT(DISTINCT s.shop_name) as shop_names
FROM orders o
LEFT JOIN users u ON o.customer_id = u.user_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
LEFT JOIN shops s ON oi.shop_id = s.shop_id
GROUP BY o.order_id;

-- =====================================================
-- SAMPLE DATA (OPTIONAL - FOR TESTING)
-- =====================================================

-- Note: This is sample data for development/testing
-- Comment out or remove in production

-- =====================================================
-- Schema Complete
-- =====================================================

-- Summary:
-- ✅ Shops table with status management
-- ✅ Categories with parent-child support
-- ✅ Products with full details and stock management
-- ✅ Product images (multiple per product)
-- ✅ Orders with status tracking
-- ✅ Order items (per shop)
-- ✅ Order status history
-- ✅ Shopping cart (database-based)
-- ✅ Wishlist
-- ✅ Product reviews and ratings
-- ✅ Notifications
-- ✅ Triggers for auto-updates
-- ✅ Views for common queries
-- ✅ Default categories inserted

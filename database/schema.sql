-- ===================================
-- TRENCART DATABASE SCHEMA
-- Three-Tier Architecture with Multi-Role Support
-- ===================================

-- Database Creation
CREATE DATABASE IF NOT EXISTS trencart_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trencart_db;

-- ===================================
-- USERS TABLE (Multi-Role Support)
-- ===================================
CREATE TABLE users (
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
) ENGINE=InnoDB;

-- ===================================
-- OTP TABLE (For Email Verification)
-- ===================================
CREATE TABLE otp_verification (
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
) ENGINE=InnoDB;

-- ===================================
-- CUSTOMER PROFILES
-- ===================================
CREATE TABLE customer_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    profile_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================================
-- SHOP PROFILES
-- ===================================
CREATE TABLE shop_profiles (
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
) ENGINE=InnoDB;

-- ===================================
-- ADMIN PROFILES
-- ===================================
CREATE TABLE admin_profiles (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    admin_level ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    permissions JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================================
-- DELIVERY BOY PROFILES (Future)
-- ===================================
CREATE TABLE delivery_profiles (
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
) ENGINE=InnoDB;

-- ===================================
-- ADDRESSES TABLE
-- ===================================
CREATE TABLE addresses (
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
) ENGINE=InnoDB;

-- ===================================
-- SESSIONS TABLE (Secure Session Management)
-- ===================================
CREATE TABLE user_sessions (
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
) ENGINE=InnoDB;

-- ===================================
-- LOGIN ATTEMPTS (Security)
-- ===================================
CREATE TABLE login_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_successful BOOLEAN DEFAULT FALSE,
    INDEX idx_email_ip (email, ip_address),
    INDEX idx_attempt_time (attempt_time)
) ENGINE=InnoDB;

-- ===================================
-- INSERT DEFAULT ADMIN USER
-- ===================================
-- Note: This is for initial setup. Password: admin123 (OTP: 123456 for testing)
INSERT INTO users (email, full_name, phone, user_type, is_verified, is_active)
VALUES ('admin@trencart.com', 'Super Admin', '9999999999', 'admin', TRUE, TRUE);

INSERT INTO admin_profiles (user_id, admin_level, permissions)
VALUES (1, 'super_admin', '{"all": true}');

-- ===================================
-- VIEWS FOR EASY QUERYING
-- ===================================

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

-- ===================================
-- CLEANUP PROCEDURES
-- ===================================

-- Delete expired OTPs
DELIMITER //
CREATE PROCEDURE cleanup_expired_otps()
BEGIN
    DELETE FROM otp_verification WHERE expires_at < NOW();
END //
DELIMITER ;

-- Delete expired sessions
DELIMITER //
CREATE PROCEDURE cleanup_expired_sessions()
BEGIN
    DELETE FROM user_sessions WHERE expires_at < NOW();
END //
DELIMITER ;

-- ===================================
-- EVENTS (Auto Cleanup - Optional)
-- ===================================
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS cleanup_otps_daily
ON SCHEDULE EVERY 1 DAY
DO CALL cleanup_expired_otps();

CREATE EVENT IF NOT EXISTS cleanup_sessions_hourly
ON SCHEDULE EVERY 1 HOUR
DO CALL cleanup_expired_sessions();

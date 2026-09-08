-- Migration: Add banners table for homepage carousel
-- Run once in phpMyAdmin

CREATE TABLE IF NOT EXISTS banners (
    banner_id     INT PRIMARY KEY AUTO_INCREMENT,
    title         VARCHAR(200) DEFAULT NULL,
    subtitle      VARCHAR(300) DEFAULT NULL,
    image_url     VARCHAR(500) NOT NULL,
    link_url      VARCHAR(500) DEFAULT NULL,
    display_order INT DEFAULT 0,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, display_order)
);

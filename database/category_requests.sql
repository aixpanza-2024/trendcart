-- Category Requests Table Migration
-- Run this in phpMyAdmin or MySQL CLI ONCE after the main schema.
-- Safe to re-run: drops and recreates the table cleanly.

DROP TABLE IF EXISTS category_requests;

CREATE TABLE category_requests (
    request_id    INT PRIMARY KEY AUTO_INCREMENT,
    shop_id       INT NOT NULL,
    user_id       INT NOT NULL,
    category_name VARCHAR(255) NOT NULL,
    parent_name   VARCHAR(255) NULL,
    note          TEXT NULL,
    status        ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   TIMESTAMP NULL,
    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

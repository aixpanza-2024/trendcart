-- Unified Login Migration
-- Run this ONCE via phpMyAdmin or MySQL CLI
-- Safe for existing data: no rows are modified, only column constraints change

ALTER TABLE users
  MODIFY COLUMN full_name VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY COLUMN phone     VARCHAR(15)  NULL     DEFAULT NULL;

-- Verify the change
DESCRIBE users;

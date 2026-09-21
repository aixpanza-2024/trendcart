-- Phone Login Migration
-- Run this ONCE via phpMyAdmin or MySQL CLI (on the live DB as well as local)
-- Safe for existing data: no rows are modified, only the column constraint changes.
-- Lets a customer account be created from a phone number alone (email stays UNIQUE;
-- MySQL allows any number of NULLs in a UNIQUE column).

ALTER TABLE users
  MODIFY COLUMN email VARCHAR(255) NULL DEFAULT NULL;

-- Verify the change
DESCRIBE users;

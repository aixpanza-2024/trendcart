-- Add delivered_at timestamp to order_items for accurate payout date tracking
ALTER TABLE order_items ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER item_status;
ALTER TABLE order_items ADD INDEX idx_delivered_at (delivered_at);

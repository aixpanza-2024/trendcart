-- Add delivered_at timestamp to order_items for accurate payout date tracking
ALTER TABLE order_items ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER item_status;
ALTER TABLE order_items ADD INDEX idx_delivered_at (delivered_at);

-- Backfill delivered_at for existing delivered order_items:
-- Use orders.delivered_at if available, else fall back to orders.order_date
UPDATE order_items oi
INNER JOIN orders o ON oi.order_id = o.order_id
SET oi.delivered_at = COALESCE(o.delivered_at, o.order_date)
WHERE oi.item_status = 'delivered'
  AND oi.delivered_at IS NULL;

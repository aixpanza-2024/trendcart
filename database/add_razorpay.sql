-- Razorpay Online Payment
-- Run this ONCE via phpMyAdmin or MySQL CLI. Safe for existing data.
--
-- razorpay_payment_id is UNIQUE: it is the idempotency guard that stops the
-- same successful payment from ever being used to create two orders (e.g. if
-- the customer's browser calls the verify endpoint twice).

ALTER TABLE orders
    ADD COLUMN razorpay_order_id   VARCHAR(64) NULL AFTER payment_method,
    ADD COLUMN razorpay_payment_id VARCHAR(64) NULL UNIQUE AFTER razorpay_order_id;

-- Verify
SHOW COLUMNS FROM orders LIKE 'razorpay%';

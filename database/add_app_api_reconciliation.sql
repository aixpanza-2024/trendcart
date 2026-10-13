-- Supports the Flutter app's payment contract: create-razorpay-order.php hands
-- back an internal_order_id immediately, and verify-payment.php exchanges that
-- id plus payment proof for the real order. Nothing is written to `orders` /
-- `order_items` (and nothing is visible to shops) until payment is verified —
-- this table only holds a priced snapshot of the cart while payment is in flight.
-- Rows older than an hour are cleaned up lazily; see create-razorpay-order.php.

CREATE TABLE IF NOT EXISTS pending_payment_orders (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    customer_id        INT NOT NULL,
    razorpay_order_id  VARCHAR(64) NOT NULL UNIQUE,
    shipping_json      LONGTEXT NOT NULL,
    items_json         LONGTEXT NOT NULL,
    coupon_code        VARCHAR(50) NULL,
    total_amount       DECIMAL(10,2) NOT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ppo_customer (customer_id),
    CONSTRAINT fk_ppo_customer FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

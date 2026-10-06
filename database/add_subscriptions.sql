-- Customer subscriptions (free delivery while active)
-- Prices live in platform_settings so admin can change them from the settings page.

CREATE TABLE IF NOT EXISTS customer_subscriptions (
    subscription_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,
    plan                 ENUM('monthly','yearly') NOT NULL,
    amount               DECIMAL(10,2) NOT NULL,
    razorpay_order_id    VARCHAR(64) NULL,
    razorpay_payment_id  VARCHAR(64) NOT NULL UNIQUE,
    starts_at            DATETIME NOT NULL,
    expires_at           DATETIME NOT NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sub_user_expiry (user_id, expires_at),
    CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_type, description) VALUES
    ('subscription_monthly_price', '99',  'number', 'Monthly subscription price (INR). Subscribers get free delivery.'),
    ('subscription_yearly_price',  '999', 'number', 'Yearly subscription price (INR). Subscribers get free delivery.');

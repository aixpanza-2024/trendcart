-- Offers / announcements posted by admin.
-- Each post fans out to the existing per-user `notifications` table (in-app inbox)
-- and, for every registered Android device, a Firebase push via device_tokens.

CREATE TABLE IF NOT EXISTS admin_notifications (
    notification_id   INT AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(120) NOT NULL,
    body              VARCHAR(500) NOT NULL,
    link_url          VARCHAR(255) NULL,
    coupon_code       VARCHAR(50) NULL,
    audience          ENUM('all','subscribers') NOT NULL DEFAULT 'all',
    recipients_count  INT NOT NULL DEFAULT 0,
    push_sent_count   INT NOT NULL DEFAULT 0,
    push_failed_count INT NOT NULL DEFAULT 0,
    push_status       VARCHAR(255) NULL,
    created_by        INT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_tokens (
    token_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    token        VARCHAR(255) NOT NULL UNIQUE,
    platform     ENUM('android','ios','web') NOT NULL DEFAULT 'android',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dt_user (user_id),
    CONSTRAINT fk_dt_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

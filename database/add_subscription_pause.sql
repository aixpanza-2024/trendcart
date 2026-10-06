-- Admin can pause a subscription. paused_at is set while paused; on resume the
-- paused time is added back to expires_at so the customer keeps their remaining days.

ALTER TABLE customer_subscriptions
    ADD COLUMN paused_at DATETIME NULL AFTER expires_at;

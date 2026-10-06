<?php
/**
 * Subscription helpers shared by pricing, profile, and payment endpoints.
 */
class SubscriptionService {

    const PLANS = ['monthly', 'yearly'];

    public static function planPrice($conn, $plan) {
        if (!in_array($plan, self::PLANS, true)) return null;
        $stmt = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = :k LIMIT 1");
        $stmt->bindValue(':k', "subscription_{$plan}_price");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? null : (float)$value;
    }

    public static function activeSubscription($conn, $user_id) {
        $stmt = $conn->prepare(
            "SELECT plan, amount, starts_at, expires_at FROM customer_subscriptions
             WHERE user_id = :uid AND expires_at > NOW()
             ORDER BY expires_at DESC LIMIT 1"
        );
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
?>

<?php
/**
 * Coupon validation + discount calculation.
 * Shared by /customer/apply-coupon.php (preview) and /customer/place-order.php
 * (final, authoritative check) so the two can never disagree.
 */

class CouponValidator {

    /**
     * @param PDO $conn
     * @param string $code
     * @param int $user_id
     * @param float $subtotal Cart subtotal (product total, before delivery/handling)
     * @return array{valid:bool, message?:string, coupon?:array, discount_amount?:float}
     */
    public static function validate($conn, $code, $user_id, $subtotal) {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['valid' => false, 'message' => 'Enter a coupon code'];
        }

        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = :code LIMIT 1");
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        $stmt->execute();
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid coupon code'];
        }
        if ((int)$coupon['is_active'] !== 1) {
            return ['valid' => false, 'message' => 'This coupon is no longer active'];
        }
        if ($coupon['valid_from'] && strtotime($coupon['valid_from']) > time()) {
            return ['valid' => false, 'message' => 'This coupon is not active yet'];
        }
        if ($coupon['valid_until'] && strtotime($coupon['valid_until']) < time()) {
            return ['valid' => false, 'message' => 'This coupon has expired'];
        }
        if ($subtotal < (float)$coupon['min_order_value']) {
            return ['valid' => false, 'message' => 'Minimum order value for this coupon is ₹' . number_format((float)$coupon['min_order_value'], 2)];
        }
        if ($coupon['usage_limit_total'] !== null && (int)$coupon['times_used'] >= (int)$coupon['usage_limit_total']) {
            return ['valid' => false, 'message' => 'This coupon has reached its usage limit'];
        }
        // usage_limit_per_user is always set (NOT NULL DEFAULT 1 in the schema) — every coupon has a per-customer cap
        $usedStmt = $conn->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = :cid AND user_id = :uid");
        $usedStmt->bindValue(':cid', $coupon['coupon_id'], PDO::PARAM_INT);
        $usedStmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $usedStmt->execute();
        if ((int)$usedStmt->fetchColumn() >= (int)$coupon['usage_limit_per_user']) {
            return ['valid' => false, 'message' => 'You have already used this coupon the maximum number of times'];
        }

        $discount = $coupon['discount_type'] === 'percentage'
            ? round($subtotal * (float)$coupon['discount_value'] / 100, 2)
            : (float)$coupon['discount_value'];

        if ($coupon['max_discount_amount'] !== null) {
            $discount = min($discount, (float)$coupon['max_discount_amount']);
        }
        // A coupon can never discount more than the subtotal itself
        $discount = min($discount, $subtotal);
        $discount = max(0, round($discount, 2));

        return ['valid' => true, 'coupon' => $coupon, 'discount_amount' => $discount];
    }

    /**
     * Record that a coupon was used on a specific order. Call inside the same
     * transaction as the order insert, after the order_id is known.
     */
    public static function recordUsage($conn, $coupon_id, $user_id, $order_id, $discount_amount) {
        $ins = $conn->prepare(
            "INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount)
             VALUES (:cid, :uid, :oid, :amt)"
        );
        $ins->bindValue(':cid', $coupon_id, PDO::PARAM_INT);
        $ins->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $ins->bindValue(':oid', $order_id, PDO::PARAM_INT);
        $ins->bindValue(':amt', $discount_amount);
        $ins->execute();

        $upd = $conn->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE coupon_id = :cid");
        $upd->bindValue(':cid', $coupon_id, PDO::PARAM_INT);
        $upd->execute();
    }
}
?>

<?php
/**
 * Customer - Browse Available Coupons
 * GET: lists currently active, in-date, not-yet-exhausted coupons.
 * Login optional — with a token, coupons the customer has already used up
 * (their own usage_limit_per_user) are left out; without one, every
 * platform-wide-available coupon is shown.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $user = TokenAuth::getUser($conn);
    $customer_id = ($user && $user['user_type'] === 'customer') ? $user['user_id'] : null;

    $stmt = $conn->prepare(
        "SELECT coupon_id, code, description, discount_type, discount_value,
                max_discount_amount, min_order_value, usage_limit_per_user, valid_until
         FROM coupons
         WHERE is_active = 1
           AND (valid_from IS NULL OR valid_from <= NOW())
           AND (valid_until IS NULL OR valid_until >= NOW())
           AND (usage_limit_total IS NULL OR times_used < usage_limit_total)
         ORDER BY
             CASE discount_type WHEN 'percentage' THEN discount_value ELSE discount_value / 10 END DESC,
             created_at DESC"
    );
    $stmt->execute();
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($customer_id && !empty($coupons)) {
        $usedStmt = $conn->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = :cid AND user_id = :uid");

        $coupons = array_values(array_filter($coupons, function ($c) use ($usedStmt, $customer_id) {
            $usedStmt->bindValue(':cid', $c['coupon_id'], PDO::PARAM_INT);
            $usedStmt->bindValue(':uid', $customer_id, PDO::PARAM_INT);
            $usedStmt->execute();
            return (int)$usedStmt->fetchColumn() < (int)$c['usage_limit_per_user'];
        }));
    }

    // usage_limit_per_user was only needed for the filter above — not customer-facing info.
    // discount/type/min_order are aliases for the Flutter app's field names, alongside
    // the original discount_type/discount_value/min_order_value fields the web uses.
    foreach ($coupons as &$c) {
        unset($c['usage_limit_per_user']);
        $c['discount']  = (float)$c['discount_value'];
        $c['type']      = $c['discount_type'] === 'percentage' ? 'percent' : 'flat';
        $c['min_order'] = (float)$c['min_order_value'];
    }
    unset($c);

    echo json_encode(['success' => true, 'data' => $coupons, 'count' => count($coupons)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

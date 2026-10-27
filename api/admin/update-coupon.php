<?php
/**
 * Admin - Update Coupon API
 * The code itself is never changed once created (it may already be printed on
 * marketing material) — only the terms and active state can be edited.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$coupon_id = (int)($data['coupon_id'] ?? 0);

if ($coupon_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'coupon_id is required']);
    exit();
}

$discount_type  = $data['discount_type'] ?? '';
$discount_value = $data['discount_value'] ?? null;

if (!in_array($discount_type, ['percentage', 'fixed', 'free_delivery'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'discount_type must be percentage, fixed or free_delivery']);
    exit();
}
// A free-delivery coupon waives the delivery charge, so it has no discount value
if ($discount_type === 'free_delivery') {
    $discount_value = 0;
} elseif (!is_numeric($discount_value) || $discount_value <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid discount value']);
    exit();
}
if ($discount_type === 'percentage' && $discount_value > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A percentage discount cannot exceed 100']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare(
        "UPDATE coupons SET
            description = :description, discount_type = :discount_type, discount_value = :discount_value,
            max_discount_amount = :max_discount, min_order_value = :min_order,
            usage_limit_total = :usage_total, usage_limit_per_user = :usage_per_user, first_order_only = :first_order_only,
            valid_from = :valid_from, valid_until = :valid_until, is_active = :is_active
         WHERE coupon_id = :id"
    );
    $stmt->bindValue(':description', trim($data['description'] ?? '') ?: null);
    $stmt->bindValue(':discount_type', $discount_type);
    $stmt->bindValue(':discount_value', (float)$discount_value);
    $stmt->bindValue(':max_discount', !empty($data['max_discount_amount']) ? (float)$data['max_discount_amount'] : null);
    $stmt->bindValue(':min_order', (float)($data['min_order_value'] ?? 0));
    $usage_total = $data['usage_limit_total'] ?? '';
    $stmt->bindValue(':usage_total', ($usage_total !== '' && $usage_total !== null) ? (int)$usage_total : null);
    $usage_per_user = $data['usage_limit_per_user'] ?? '';
    // Column is NOT NULL DEFAULT 1 — an empty/omitted value means "1 per customer", not unlimited
    $stmt->bindValue(':usage_per_user', ($usage_per_user !== '' && $usage_per_user !== null) ? (int)$usage_per_user : 1, PDO::PARAM_INT);
    $stmt->bindValue(':first_order_only', !empty($data['first_order_only']) ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':valid_from', !empty($data['valid_from']) ? $data['valid_from'] : null);
    $stmt->bindValue(':valid_until', !empty($data['valid_until']) ? $data['valid_until'] : null);
    $stmt->bindValue(':is_active', !empty($data['is_active']) ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':id', $coupon_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Either the row doesn't exist, or nothing actually changed — check which
        $chk = $conn->prepare("SELECT coupon_id FROM coupons WHERE coupon_id = :id LIMIT 1");
        $chk->bindValue(':id', $coupon_id, PDO::PARAM_INT);
        $chk->execute();
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Coupon not found']);
            exit();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Coupon updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

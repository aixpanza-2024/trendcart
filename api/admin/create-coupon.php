<?php
/**
 * Admin - Create Coupon API
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

$code           = strtoupper(trim($data['code'] ?? ''));
$discount_type  = $data['discount_type'] ?? '';
$discount_value = $data['discount_value'] ?? null;

if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code must be 3-50 characters: letters, numbers, - or _']);
    exit();
}
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

    $existing = $conn->prepare("SELECT coupon_id FROM coupons WHERE code = :code LIMIT 1");
    $existing->bindValue(':code', $code, PDO::PARAM_STR);
    $existing->execute();
    if ($existing->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A coupon with this code already exists']);
        exit();
    }

    $stmt = $conn->prepare(
        "INSERT INTO coupons
            (code, description, discount_type, discount_value, max_discount_amount, min_order_value,
             usage_limit_total, usage_limit_per_user, first_order_only, valid_from, valid_until, is_active, created_by)
         VALUES
            (:code, :description, :discount_type, :discount_value, :max_discount, :min_order,
             :usage_total, :usage_per_user, :first_order_only, :valid_from, :valid_until, :is_active, :created_by)"
    );
    $stmt->bindValue(':code', $code);
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
    $stmt->bindValue(':created_by', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Coupon created', 'data' => ['coupon_id' => $conn->lastInsertId()]]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

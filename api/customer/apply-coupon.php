<?php
/**
 * Customer - Validate a coupon code against the current cart (preview only).
 * POST: { code, subtotal }
 * Does NOT redeem the coupon — /customer/place-order.php re-validates and
 * actually records usage when the order is placed. subtotal here is the
 * client's cart total, purely for showing the discount before checkout;
 * place-order.php never trusts it and recalculates from the database.
 * Requires customer session/token.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/CouponValidator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$user_id = TokenAuth::requireCustomer($conn, 'Please login to apply a coupon');

$data = json_decode(file_get_contents('php://input'), true);
$code     = trim($data['code'] ?? '');
$subtotal = (float)($data['subtotal'] ?? 0);

if ($subtotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
    exit;
}

try {
    $result = CouponValidator::validate($conn, $code, $user_id, $subtotal);

    if (!$result['valid']) {
        echo json_encode(['success' => false, 'message' => $result['message']]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Coupon applied',
        'data' => [
            'code'            => $result['coupon']['code'],
            'discount_amount' => $result['discount_amount'],
            'description'     => $result['coupon']['description'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

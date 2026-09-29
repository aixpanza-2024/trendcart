<?php
/**
 * Customer - Place Order API (Cash on Delivery only)
 * Requires customer session
 * POST: { shipping: {...}, items: [{id, quantity}], payment_method: "cod", coupon_code?: "" }
 *
 * Online payment does NOT go through this file — see create-razorpay-order.php
 * and verify-razorpay-payment.php. Both paths share their actual pricing and
 * order-creation logic via OrderService so they can never disagree.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/OrderService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login to place an order');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$shipping       = $input['shipping']       ?? [];
$cart_items     = $input['items']          ?? [];
$payment_method = $input['payment_method'] ?? 'cod';
$coupon_code    = trim($input['coupon_code'] ?? '');

if ($payment_method !== 'cod') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Use the online-payment endpoints for non-COD orders']);
    exit;
}

try {
    $psStmt = $conn->query(
        "SELECT setting_key, setting_value FROM platform_settings
         WHERE setting_key IN ('cod_enabled')"
    );
    $psRows = $psStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $cod_enabled = (int)($psRows['cod_enabled'] ?? 1) === 1;

    if (!$cod_enabled) {
        echo json_encode(['success' => false, 'message' => 'Cash on Delivery is currently not available. Please try again later.']);
        exit;
    }

    $result = OrderService::placeOrder(
        $conn, $customer_id, $shipping, $cart_items, $coupon_code,
        'cod', 'pending'
    );

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    echo json_encode($result);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

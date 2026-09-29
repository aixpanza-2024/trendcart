<?php
/**
 * Customer - Create Razorpay Order (Step 1 of online payment)
 * POST: { shipping: {...}, items: [{id, quantity}], coupon_code?: "" }
 *
 * Prices the cart (same validated pricing as Cash on Delivery) and opens a
 * Razorpay Order for that amount. Nothing is written to our own `orders`
 * table here — if the customer abandons the payment, nothing is left behind.
 * The real order is only created by verify-razorpay-payment.php once a
 * payment has actually been confirmed.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/OrderService.php';
require_once __DIR__ . '/../utils/RazorpayClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login to pay online');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$shipping    = $input['shipping']    ?? [];
$cart_items  = $input['items']       ?? [];
$coupon_code = trim($input['coupon_code'] ?? '');

try {
    $psStmt = $conn->query(
        "SELECT setting_key, setting_value FROM platform_settings
         WHERE setting_key IN ('online_payment_enabled')"
    );
    $psRows = $psStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $online_enabled = (int)($psRows['online_payment_enabled'] ?? 1) === 1;

    if (!$online_enabled) {
        echo json_encode(['success' => false, 'message' => 'Online payment is currently not available. Please use Cash on Delivery.']);
        exit;
    }

    $priced = OrderService::priceCart($conn, $customer_id, $shipping, $cart_items, $coupon_code);
    if (!$priced['success']) {
        echo json_encode($priced);
        exit;
    }

    $razorpay = new RazorpayClient();
    $receipt  = 'tc_' . $customer_id . '_' . time();
    $rzpOrder = $razorpay->createOrder($priced['total_amount'], $receipt, [
        'customer_id' => (string)$customer_id,
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'razorpay_order_id' => $rzpOrder['id'],
            'amount'            => $rzpOrder['amount'],   // paise, exactly what Razorpay expects in Checkout
            'currency'          => $rzpOrder['currency'],
            'key_id'            => $razorpay->getKeyId(),
            'total_amount'      => $priced['total_amount'], // rupees, for display only
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

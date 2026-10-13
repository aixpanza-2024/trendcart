<?php
/**
 * Customer - Create Razorpay Order (Step 1 of online payment)
 * POST: { shipping: {...}, items: [{id, quantity}], coupon_code?: "" }
 *
 * Prices the cart (same validated pricing as Cash on Delivery) and opens a
 * Razorpay Order for that amount. Nothing is written to our own `orders`
 * table here — if the customer abandons the payment, nothing is left behind.
 * The real order is only created once a payment has actually been confirmed,
 * by either verify-razorpay-payment.php (web) or verify-payment.php (app).
 *
 * A priced snapshot of the cart is saved to pending_payment_orders so the
 * app can hand back just its id (internal_order_id) at the verify step
 * instead of resending the whole cart. The web flow ignores this id and
 * keeps resending the cart to verify-razorpay-payment.php as before.
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

    // Lazy cleanup: stale reservations (abandoned payments) never block
    // anything since verify-payment.php checks its own age, but there's no
    // reason to let old rows pile up.
    $conn->exec("DELETE FROM pending_payment_orders WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

    $reserve = $conn->prepare(
        "INSERT INTO pending_payment_orders (customer_id, razorpay_order_id, shipping_json, items_json, coupon_code, total_amount)
         VALUES (:cid, :rzp_id, :shipping, :items, :coupon, :total)"
    );
    $reserve->execute([
        ':cid'      => $customer_id,
        ':rzp_id'   => $rzpOrder['id'],
        ':shipping' => json_encode($shipping),
        ':items'    => json_encode($cart_items),
        ':coupon'   => $coupon_code !== '' ? $coupon_code : null,
        ':total'    => $priced['total_amount'],
    ]);
    $internal_order_id = (int)$conn->lastInsertId();

    echo json_encode([
        'success' => true,
        // Top-level fields for the Flutter app's contract:
        'internal_order_id' => $internal_order_id,
        'razorpay_order_id' => $rzpOrder['id'],
        'amount_paise'      => $rzpOrder['amount'],
        'currency'          => $rzpOrder['currency'],
        'key_id'            => $razorpay->getKeyId(),
        // Nested for the existing web checkout flow — unchanged shape:
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

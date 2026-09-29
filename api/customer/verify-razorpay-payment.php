<?php
/**
 * Customer - Verify Razorpay Payment and Place the Order (Step 2 of online payment)
 * POST: { razorpay_order_id, razorpay_payment_id, razorpay_signature,
 *         shipping: {...}, items: [...], coupon_code?: "" }
 *
 * Trust chain, in order:
 *  1. HMAC signature proves these three fields genuinely came from Razorpay.
 *  2. Fetching the payment BY ID from Razorpay's API (server to server) is the
 *     authoritative source for its real status and amount — the signature alone
 *     does not confirm either.
 *  3. The cart is re-priced fresh from the database right now and must match
 *     the amount Razorpay actually captured, or the order is refused. This
 *     catches both tampering and ordinary staleness (a price/coupon/stock
 *     change between opening Checkout and completing payment).
 *  4. razorpay_payment_id is UNIQUE on orders — a repeated call with the same
 *     payment (e.g. a double-submitted request) can never create two orders.
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

$customer_id = TokenAuth::requireCustomer($conn, 'Please login to complete payment');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$razorpay_order_id   = trim($input['razorpay_order_id']   ?? '');
$razorpay_payment_id = trim($input['razorpay_payment_id'] ?? '');
$razorpay_signature  = trim($input['razorpay_signature']  ?? '');
$shipping    = $input['shipping'] ?? [];
$cart_items  = $input['items']    ?? [];
$coupon_code = trim($input['coupon_code'] ?? '');

if ($razorpay_order_id === '' || $razorpay_payment_id === '' || $razorpay_signature === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing payment confirmation details']);
    exit;
}

try {
    // Idempotency: if this exact payment already created an order, hand that back
    // instead of doing any further work (covers a retried/duplicate verify call).
    $existing = $conn->prepare("SELECT order_id, order_number, total_amount, shipping_amount, discount_amount, coupon_code FROM orders WHERE razorpay_payment_id = :pid LIMIT 1");
    $existing->bindValue(':pid', $razorpay_payment_id);
    $existing->execute();
    if ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'success'         => true,
            'order_id'        => (int)$row['order_id'],
            'order_number'    => $row['order_number'],
            'total'           => (float)$row['total_amount'],
            'shipping_amount' => (float)$row['shipping_amount'],
            'discount_amount' => (float)$row['discount_amount'],
            'coupon_code'     => $row['coupon_code'],
            'already_existed' => true,
        ]);
        exit;
    }

    $razorpay = new RazorpayClient();

    if (!$razorpay->verifySignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment verification failed. If money was deducted, it will be refunded automatically.']);
        exit;
    }

    // Authoritative check — ask Razorpay directly what this payment actually is
    $payment = $razorpay->fetchPayment($razorpay_payment_id);

    if (($payment['order_id'] ?? null) !== $razorpay_order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment does not match this order']);
        exit;
    }
    if (($payment['status'] ?? null) !== 'captured') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment was not captured (status: ' . ($payment['status'] ?? 'unknown') . '). Please try again or contact support.']);
        exit;
    }

    // Re-price the cart fresh and require it to match what was actually captured —
    // never trust a total the client sends, and never trust the amount from step 1
    // either, since stock/price/coupon state can change while Checkout is open.
    $priced = OrderService::priceCart($conn, $customer_id, $shipping, $cart_items, $coupon_code);
    if (!$priced['success']) {
        http_response_code(400);
        echo json_encode($priced);
        exit;
    }

    $expectedPaise = (int)round($priced['total_amount'] * 100);
    if ((int)$payment['amount'] !== $expectedPaise) {
        error_log("Razorpay amount mismatch: captured {$payment['amount']} paise, cart now prices to {$expectedPaise} paise (payment_id={$razorpay_payment_id})");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The order total has changed since payment was made. Please contact support with your payment ID: ' . $razorpay_payment_id]);
        exit;
    }

    $result = OrderService::placeOrder(
        $conn, $customer_id, $shipping, $cart_items, $coupon_code,
        'online', 'paid', $razorpay_order_id, $razorpay_payment_id
    );

    echo json_encode($result);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * Customer - Verify Razorpay Payment for the app flow (Step 2)
 * POST: { internal_order_id, razorpay_order_id, razorpay_payment_id, razorpay_signature }
 *
 * Counterpart to create-razorpay-order.php's reservation: internal_order_id
 * is a pending_payment_orders row holding the priced cart snapshot from
 * step 1. Same trust chain as verify-razorpay-payment.php (the web flow):
 *  1. HMAC signature proves these fields came from Razorpay.
 *  2. The payment is fetched BY ID from Razorpay's API (server to server) —
 *     the authoritative source for its real status and amount.
 *  3. The cart snapshot is re-priced fresh right now and must match what
 *     Razorpay actually captured — catches tampering and ordinary staleness
 *     (price/coupon/stock changed while the app's payment sheet was open).
 *  4. razorpay_payment_id is UNIQUE on orders — a retried call can never
 *     create two orders.
 *
 * Nothing is written to `orders` until this step succeeds, so an abandoned
 * payment leaves nothing behind except its (self-expiring) reservation row.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/OrderService.php';
require_once __DIR__ . '/../utils/RazorpayClient.php';

const RESERVATION_LIFETIME_MINUTES = 30;

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

$internal_order_id   = (int)($input['internal_order_id'] ?? 0);
$razorpay_order_id   = trim($input['razorpay_order_id']   ?? '');
$razorpay_payment_id = trim($input['razorpay_payment_id'] ?? '');
$razorpay_signature  = trim($input['razorpay_signature']  ?? '');

if ($internal_order_id <= 0 || $razorpay_order_id === '' || $razorpay_payment_id === '' || $razorpay_signature === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing payment confirmation details']);
    exit;
}

try {
    // Idempotency: a retried call for a payment that already placed an order
    // hands that order back instead of trying (and failing) to place it again.
    $existing = $conn->prepare("SELECT order_id, order_number, order_status, payment_status FROM orders WHERE razorpay_payment_id = :pid LIMIT 1");
    $existing->bindValue(':pid', $razorpay_payment_id);
    $existing->execute();
    if ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'success'         => true,
            'order_id'        => (int)$row['order_id'],
            'order_number'    => $row['order_number'],
            'order_status'    => $row['order_status'],
            'payment_status'  => $row['payment_status'],
            'already_existed' => true,
        ]);
        exit;
    }

    // age_minutes is computed by MySQL against its own NOW(), never compared
    // against PHP's clock — the two can run in different timezones.
    $reservation = $conn->prepare(
        "SELECT *, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS age_minutes
         FROM pending_payment_orders WHERE id = :id AND customer_id = :cid LIMIT 1"
    );
    $reservation->bindValue(':id', $internal_order_id, PDO::PARAM_INT);
    $reservation->bindValue(':cid', $customer_id, PDO::PARAM_INT);
    $reservation->execute();
    $pending = $reservation->fetch(PDO::FETCH_ASSOC);

    if (!$pending) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment session not found. Please start checkout again.']);
        exit;
    }

    if ((int)$pending['age_minutes'] > RESERVATION_LIFETIME_MINUTES) {
        $conn->prepare("DELETE FROM pending_payment_orders WHERE id = :id")->execute([':id' => $internal_order_id]);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This payment session has expired. Please start checkout again.']);
        exit;
    }

    if ($pending['razorpay_order_id'] !== $razorpay_order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment does not match this order']);
        exit;
    }

    $razorpay = new RazorpayClient();

    if (!$razorpay->verifySignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment verification failed. If money was deducted, it will be refunded automatically.']);
        exit;
    }

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

    $shipping    = json_decode($pending['shipping_json'], true) ?? [];
    $cart_items  = json_decode($pending['items_json'], true) ?? [];
    $coupon_code = $pending['coupon_code'] ?? '';

    // Re-price fresh — never trust the amount from step 1, since stock,
    // price, or coupon validity can change while the app's payment sheet is open.
    $priced = OrderService::priceCart($conn, $customer_id, $shipping, $cart_items, $coupon_code);
    if (!$priced['success']) {
        http_response_code(400);
        echo json_encode($priced);
        exit;
    }

    $expectedPaise = (int)round($priced['total_amount'] * 100);
    if ((int)$payment['amount'] !== $expectedPaise) {
        error_log("App Razorpay amount mismatch: captured {$payment['amount']} paise, cart now prices to {$expectedPaise} paise (payment_id={$razorpay_payment_id})");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The order total has changed since payment was made. Please contact support with your payment ID: ' . $razorpay_payment_id]);
        exit;
    }

    $result = OrderService::placeOrder(
        $conn, $customer_id, $shipping, $cart_items, $coupon_code,
        'online', 'paid', $razorpay_order_id, $razorpay_payment_id
    );

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    $conn->prepare("DELETE FROM pending_payment_orders WHERE id = :id")->execute([':id' => $internal_order_id]);

    echo json_encode([
        'success'        => true,
        'message'        => 'Payment verified successfully',
        'order_id'       => $result['order_id'],
        'order_number'   => $result['order_number'],
        'order_status'   => 'pending',
        'payment_status' => 'paid',
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

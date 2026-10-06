<?php
/**
 * Customer - Verify subscription payment and activate the subscription (step 2)
 * POST: { razorpay_order_id, razorpay_payment_id, razorpay_signature, plan }
 *
 * Same trust chain as verify-razorpay-payment.php: HMAC signature, then the
 * payment fetched server to server must be captured, belong to this customer
 * and plan (from the order notes set in create-subscription-order.php), and
 * carry the exact plan price. razorpay_payment_id is UNIQUE, so a repeated
 * call can never create a second subscription.
 *
 * Renewing while still active stacks the new period after the current expiry.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/SubscriptionService.php';
require_once __DIR__ . '/../utils/RazorpayClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login to complete payment');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$razorpay_order_id   = trim($input['razorpay_order_id']   ?? '');
$razorpay_payment_id = trim($input['razorpay_payment_id'] ?? '');
$razorpay_signature  = trim($input['razorpay_signature']  ?? '');
$plan                = $input['plan'] ?? '';

if ($razorpay_order_id === '' || $razorpay_payment_id === '' || $razorpay_signature === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing payment confirmation details']);
    exit;
}

try {
    $existing = $conn->prepare("SELECT plan, amount, starts_at, expires_at FROM customer_subscriptions WHERE razorpay_payment_id = :pid LIMIT 1");
    $existing->bindValue(':pid', $razorpay_payment_id);
    $existing->execute();
    if ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'already_existed' => true, 'data' => $row]);
        exit;
    }

    $price = SubscriptionService::planPrice($conn, $plan);
    if ($price === null || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid subscription plan']);
        exit;
    }

    $razorpay = new RazorpayClient();

    if (!$razorpay->verifySignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment verification failed']);
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
        echo json_encode(['success' => false, 'message' => 'Payment was not captured. Please try again.']);
        exit;
    }
    if (($payment['notes']['customer_id'] ?? null) !== (string)$customer_id
        || ($payment['notes']['plan'] ?? null) !== $plan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment does not match this subscription']);
        exit;
    }
    if ((int)$payment['amount'] !== (int)round($price * 100)) {
        error_log("Subscription amount mismatch: captured {$payment['amount']} paise, plan {$plan} is {$price} (payment_id={$razorpay_payment_id})");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subscription price has changed since payment. Please contact support with payment ID: ' . $razorpay_payment_id]);
        exit;
    }

    $interval = $plan === 'yearly' ? '1 YEAR' : '1 MONTH';
    $insert = $conn->prepare(
        "INSERT INTO customer_subscriptions
            (user_id, plan, amount, razorpay_order_id, razorpay_payment_id, starts_at, expires_at)
         SELECT :uid, :plan, :amount, :oid, :pid, base.start_at,
                DATE_ADD(base.start_at, INTERVAL {$interval})
         FROM (SELECT COALESCE(
                  (SELECT MAX(expires_at) FROM customer_subscriptions
                    WHERE user_id = :uid_base AND expires_at > NOW()),
                  NOW()) AS start_at) base"
    );
    $insert->bindValue(':uid',      $customer_id, PDO::PARAM_INT);
    $insert->bindValue(':uid_base', $customer_id, PDO::PARAM_INT);
    $insert->bindValue(':plan',     $plan);
    $insert->bindValue(':amount',   $price);
    $insert->bindValue(':oid',      $razorpay_order_id);
    $insert->bindValue(':pid',      $razorpay_payment_id);
    $insert->execute();

    $active = SubscriptionService::activeSubscription($conn, $customer_id);
    echo json_encode(['success' => true, 'already_existed' => false, 'data' => $active]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

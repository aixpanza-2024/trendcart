<?php
/**
 * Customer - Create Razorpay Order for a subscription (step 1)
 * POST: { plan: "monthly" | "yearly" }
 *
 * Nothing is written to customer_subscriptions here. The subscription is only
 * recorded by verify-subscription-payment.php after the payment is captured.
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

$customer_id = TokenAuth::requireCustomer($conn, 'Please login to subscribe');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$plan  = $input['plan'] ?? '';

try {
    $price = SubscriptionService::planPrice($conn, $plan);
    if ($price === null || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid subscription plan']);
        exit;
    }

    $razorpay = new RazorpayClient();
    $receipt  = 'sub_' . $customer_id . '_' . time();
    $rzpOrder = $razorpay->createOrder($price, $receipt, [
        'customer_id' => (string)$customer_id,
        'plan'        => $plan,
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'razorpay_order_id' => $rzpOrder['id'],
            'amount'            => $rzpOrder['amount'],
            'currency'          => $rzpOrder['currency'],
            'key_id'            => $razorpay->getKeyId(),
            'plan'              => $plan,
            'price'             => $price,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

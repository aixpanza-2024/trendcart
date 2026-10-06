<?php
/**
 * Customer - Subscription status and plan prices
 * GET → { plans: {monthly: price, yearly: price}, active: {plan, expires_at} | null }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/SubscriptionService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login');

try {
    echo json_encode([
        'success' => true,
        'data' => [
            'plans' => [
                'monthly' => SubscriptionService::planPrice($conn, 'monthly'),
                'yearly'  => SubscriptionService::planPrice($conn, 'yearly'),
            ],
            'active' => SubscriptionService::activeSubscription($conn, $customer_id),
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

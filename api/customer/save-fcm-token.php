<?php
/**
 * Customer - Save FCM push token (Flutter app contract)
 * POST: { fcm_token: "..." }
 *
 * Thin alias over device-token.php's register action, at the exact path and
 * payload shape the Android app already calls. Always registers as 'android'
 * — this endpoint exists specifically for the mobile app.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$token = trim($input['fcm_token'] ?? $input['token'] ?? '');

if ($token === '' || strlen($token) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid device token']);
    exit;
}

try {
    $conn->prepare(
        "INSERT INTO device_tokens (user_id, token, platform) VALUES (:uid, :token, 'android')
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
    )->execute([':uid' => $customer_id, ':token' => $token]);

    echo json_encode(['success' => true, 'message' => 'Device registered']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

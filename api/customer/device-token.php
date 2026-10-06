<?php
/**
 * Customer - Register or remove a push-notification device token
 * POST: { action: "register", token, platform?: "android"|"ios"|"web" }
 *       { action: "unregister", token }   (call on logout)
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

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $input['action'] ?? '';
$token    = trim($input['token'] ?? '');
$platform = $input['platform'] ?? 'android';

if ($token === '' || strlen($token) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid device token']);
    exit;
}

try {
    if ($action === 'register') {
        if (!in_array($platform, ['android', 'ios', 'web'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid platform']);
            exit;
        }
        $conn->prepare(
            "INSERT INTO device_tokens (user_id, token, platform) VALUES (:uid, :token, :platform)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform)"
        )->execute([':uid' => $customer_id, ':token' => $token, ':platform' => $platform]);

        echo json_encode(['success' => true, 'message' => 'Device registered']);
        exit;
    }

    if ($action === 'unregister') {
        $conn->prepare("DELETE FROM device_tokens WHERE token = :token AND user_id = :uid")
             ->execute([':token' => $token, ':uid' => $customer_id]);

        echo json_encode(['success' => true, 'message' => 'Device removed']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

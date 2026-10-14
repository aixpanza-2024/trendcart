<?php
/**
 * Customer - Offer notification preference
 * GET  → { notifications_enabled: bool }
 * POST → { notifications_enabled: bool }
 *
 * Only covers offer notifications (admin posts + push). Order/account
 * notifications aren't affected by this flag.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

$database = new Database();
$conn = $database->getConnection();

$user_id = TokenAuth::requireCustomer($conn, 'Please login');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("SELECT notifications_enabled FROM customer_profiles WHERE user_id = :uid LIMIT 1");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'data' => [
            'notifications_enabled' => $value === false ? true : (bool)$value,
        ]]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input   = json_decode(file_get_contents('php://input'), true) ?? [];
        $enabled = !empty($input['notifications_enabled']) ? 1 : 0;

        $conn->prepare(
            "INSERT INTO customer_profiles (user_id, notifications_enabled)
             VALUES (:uid, :enabled)
             ON DUPLICATE KEY UPDATE notifications_enabled = VALUES(notifications_enabled)"
        )->execute([':uid' => $user_id, ':enabled' => $enabled]);

        echo json_encode(['success' => true, 'message' => 'Preference saved']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

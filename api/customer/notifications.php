<?php
/**
 * Customer - In-app notification inbox
 * GET  → { unread_count, items: [...latest 50] }
 * POST → { action: "read", notification_id? }  (no id = mark all as read)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare(
            "SELECT n.notification_id, n.notification_type, n.title, n.message, n.is_read, n.created_at,
                    an.link_url, an.coupon_code
             FROM notifications n
             LEFT JOIN admin_notifications an
                    ON n.related_type = 'admin_notification' AND an.notification_id = n.related_id
             WHERE n.user_id = :uid
             ORDER BY n.created_at DESC, n.notification_id DESC LIMIT 50"
        );
        $stmt->bindValue(':uid', $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unread = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $unread->bindValue(':uid', $customer_id, PDO::PARAM_INT);
        $unread->execute();

        echo json_encode(['success' => true, 'data' => [
            'unread_count' => (int)$unread->fetchColumn(),
            'items'        => $items,
        ]]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (($input['action'] ?? '') !== 'read') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
        }

        $id = (int)($input['notification_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND notification_id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        } else {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        }
        $stmt->bindValue(':uid', $customer_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Marked as read']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

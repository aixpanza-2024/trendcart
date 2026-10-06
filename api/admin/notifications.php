<?php
/**
 * Admin - Offers & push notifications
 * GET  → recent posts with delivery counts
 * POST → { title, body, link_url?, coupon_code?, audience: "all" | "subscribers" }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/NotificationService.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = $conn->query(
            "SELECT notification_id, title, body, link_url, coupon_code, audience,
                    recipients_count, push_sent_count, push_failed_count, push_status, created_at
             FROM admin_notifications ORDER BY created_at DESC, notification_id DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $title    = trim($body['title'] ?? '');
    $message  = trim($body['body'] ?? '');
    $link     = trim($body['link_url'] ?? '');
    $coupon   = strtoupper(trim($body['coupon_code'] ?? ''));
    $audience = $body['audience'] ?? 'all';

    if ($title === '' || mb_strlen($title) > 120) {
        echo json_encode(['success' => false, 'message' => 'Title is required (max 120 characters)']);
        exit();
    }
    if ($message === '' || mb_strlen($message) > 500) {
        echo json_encode(['success' => false, 'message' => 'Message is required (max 500 characters)']);
        exit();
    }
    if ($link !== '' && !preg_match('#^(https://|pages/)\S+$#', $link)) {
        echo json_encode(['success' => false, 'message' => 'Link must start with https:// or pages/']);
        exit();
    }
    if ($coupon !== '') {
        if (!preg_match('/^[A-Z0-9_-]{1,50}$/', $coupon)) {
            echo json_encode(['success' => false, 'message' => 'Coupon code can only contain letters, numbers, - and _']);
            exit();
        }
        $couponCheck = $conn->prepare("SELECT 1 FROM coupons WHERE code = :code AND is_active = 1 LIMIT 1");
        $couponCheck->bindValue(':code', $coupon);
        $couponCheck->execute();
        if (!$couponCheck->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'That coupon code does not exist or is not active']);
            exit();
        }
    }
    if (!in_array($audience, ['all', 'subscribers'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid audience']);
        exit();
    }

    $result = NotificationService::publish($conn, [
        'title'       => $title,
        'body'        => $message,
        'link_url'    => $link !== '' ? $link : null,
        'coupon_code' => $coupon !== '' ? $coupon : null,
        'audience'    => $audience,
    ], $_SESSION['user_id'] ?? null);

    echo json_encode(['success' => true, 'message' => 'Offer published', 'data' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

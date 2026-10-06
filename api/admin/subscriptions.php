<?php
/**
 * Admin - Customer subscriptions
 * GET  → list recent subscriptions with status (active / paused / expired)
 * POST → { action: "pause" | "resume", subscription_id }
 *
 * Pausing freezes the remaining time. Resuming adds the paused duration back
 * onto expires_at, so the customer keeps the days they had left.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

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
            "SELECT s.subscription_id, s.user_id, s.plan, s.amount, s.starts_at, s.expires_at, s.paused_at,
                    u.full_name, u.email, u.phone,
                    CASE
                        WHEN s.paused_at IS NOT NULL THEN 'paused'
                        WHEN s.expires_at > NOW()    THEN 'active'
                        ELSE 'expired'
                    END AS status
             FROM customer_subscriptions s
             JOIN users u ON u.user_id = s.user_id
             ORDER BY s.created_at DESC
             LIMIT 300"
        )->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';
        $id     = (int)($body['subscription_id'] ?? 0);

        if ($id <= 0 || !in_array($action, ['pause', 'resume'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit();
        }

        if ($action === 'pause') {
            $stmt = $conn->prepare(
                "UPDATE customer_subscriptions SET paused_at = NOW()
                 WHERE subscription_id = :id AND paused_at IS NULL AND expires_at > NOW()"
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $message = 'Subscription paused';
        } else {
            $stmt = $conn->prepare(
                "UPDATE customer_subscriptions
                 SET expires_at = DATE_ADD(expires_at, INTERVAL TIMESTAMPDIFF(SECOND, paused_at, NOW()) SECOND),
                     paused_at  = NULL
                 WHERE subscription_id = :id AND paused_at IS NOT NULL"
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $message = 'Subscription resumed';
        }

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Nothing to ' . $action . ' for this subscription']);
            exit();
        }

        echo json_encode(['success' => true, 'message' => $message]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

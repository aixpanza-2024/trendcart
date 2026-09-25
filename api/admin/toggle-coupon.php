<?php
/**
 * Admin - Activate / Deactivate a Coupon
 * POST: { coupon_id, is_active }
 * Coupons are deactivated rather than deleted — coupon_usage rows reference
 * them and a hard delete would either break FK integrity or erase order history.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$coupon_id = (int)($data['coupon_id'] ?? 0);
$is_active = !empty($data['is_active']) ? 1 : 0;

if ($coupon_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'coupon_id is required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("UPDATE coupons SET is_active = :active WHERE coupon_id = :id");
    $stmt->bindValue(':active', $is_active, PDO::PARAM_INT);
    $stmt->bindValue(':id', $coupon_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => $is_active ? 'Coupon activated' : 'Coupon deactivated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

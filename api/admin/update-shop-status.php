<?php
/**
 * Admin Update Shop Status API
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['shop_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Shop ID and status required']);
    exit();
}

if (!in_array($data['status'], ['open', 'closed', 'suspended'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get old status for logging
    $stmt = $conn->prepare("SELECT shop_status FROM shops WHERE shop_id = :id");
    $stmt->bindParam(':id', $data['shop_id']);
    $stmt->execute();
    $old = $stmt->fetch(PDO::FETCH_ASSOC);

    // Update status
    $stmt = $conn->prepare("UPDATE shops SET shop_status = :status WHERE shop_id = :id");
    $stmt->bindParam(':status', $data['status']);
    $stmt->bindParam(':id', $data['shop_id']);
    $stmt->execute();

    // Also update user account if suspended
    if ($data['status'] === 'suspended') {
        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE user_id = (SELECT user_id FROM shops WHERE shop_id = :id)");
        $stmt->bindParam(':id', $data['shop_id']);
        $stmt->execute();
    } elseif ($old && $old['shop_status'] === 'suspended') {
        $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE user_id = (SELECT user_id FROM shops WHERE shop_id = :id)");
        $stmt->bindParam(':id', $data['shop_id']);
        $stmt->execute();
    }

    // Log activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, description, old_value, new_value, ip_address)
        VALUES (:admin_id, 'update_status', 'shop', :entity_id, 'Shop status changed', :old_val, :new_val, :ip)
    ");
    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $stmt->bindParam(':entity_id', $data['shop_id']);
    $old_val = $old ? $old['shop_status'] : '';
    $stmt->bindParam(':old_val', $old_val);
    $stmt->bindParam(':new_val', $data['status']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt->bindParam(':ip', $ip);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Shop status updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

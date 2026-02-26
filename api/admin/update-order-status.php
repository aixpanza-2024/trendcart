<?php
/**
 * Admin Update Order Status API
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

if (!$data || !isset($data['order_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID and status required']);
    exit();
}

$valid_statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned', 'refunded'];
if (!in_array($data['status'], $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $conn->beginTransaction();

    // Get old status
    $stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = :id");
    $stmt->bindParam(':id', $data['order_id']);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Update order status
    $update_fields = "order_status = :status";
    $params = [':status' => $data['status'], ':id' => $data['order_id']];

    // Set timestamps based on status
    switch ($data['status']) {
        case 'confirmed':
            $update_fields .= ", confirmed_at = NOW()";
            break;
        case 'shipped':
            $update_fields .= ", shipped_at = NOW()";
            if (!empty($data['tracking_number'])) {
                $update_fields .= ", tracking_number = :tracking";
                $params[':tracking'] = $data['tracking_number'];
            }
            break;
        case 'delivered':
            $update_fields .= ", delivered_at = NOW()";
            break;
        case 'cancelled':
            $update_fields .= ", cancelled_at = NOW()";
            break;
    }

    $stmt = $conn->prepare("UPDATE orders SET $update_fields WHERE order_id = :id");
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();

    // Also update all order items
    $stmt = $conn->prepare("UPDATE order_items SET item_status = :status WHERE order_id = :id");
    $stmt->bindParam(':status', $data['status']);
    $stmt->bindParam(':id', $data['order_id']);
    $stmt->execute();

    // Log status change
    $stmt = $conn->prepare("
        INSERT INTO order_status_history (order_id, status_from, status_to, changed_by, change_note)
        VALUES (:order_id, :from, :to, :by, :note)
    ");
    $stmt->bindParam(':order_id', $data['order_id']);
    $stmt->bindParam(':from', $order['order_status']);
    $stmt->bindParam(':to', $data['status']);
    $stmt->bindParam(':by', $_SESSION['user_id']);
    $note = $data['note'] ?? 'Status updated by admin';
    $stmt->bindParam(':note', $note);
    $stmt->execute();

    // Log admin activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, description, old_value, new_value, ip_address)
        VALUES (:admin_id, 'update_status', 'order', :entity_id, 'Order status changed', :old, :new, :ip)
    ");
    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $stmt->bindParam(':entity_id', $data['order_id']);
    $stmt->bindParam(':old', $order['order_status']);
    $stmt->bindParam(':new', $data['status']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt->bindParam(':ip', $ip);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

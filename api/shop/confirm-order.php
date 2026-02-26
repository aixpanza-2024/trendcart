<?php
/**
 * Confirm Order API
 * Shop owner confirms/accepts an order
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Include required files
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login.'
    ]);
    exit();
}

// Check if user is shop owner
if ($_SESSION['user_type'] !== 'shop') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Shop owners only.'
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_item_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Order item ID is required.'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$order_item_id = $data['order_item_id'];

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Begin transaction
    $conn->beginTransaction();

    // Get shop_id for this user
    $query = "SELECT shop_id FROM shops WHERE user_id = :user_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        throw new Exception('Shop not found');
    }

    $shop_id = $shop['shop_id'];

    // Verify that this order item belongs to this shop
    $query = "SELECT * FROM order_items WHERE order_item_id = :order_item_id AND shop_id = :shop_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':order_item_id', $order_item_id);
    $stmt->bindParam(':shop_id', $shop_id);
    $stmt->execute();
    $order_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order_item) {
        throw new Exception('Order item not found or access denied');
    }

    // Check if already confirmed
    if ($order_item['item_status'] !== 'pending') {
        throw new Exception('Order already processed');
    }

    // Update order item status to confirmed
    $query = "UPDATE order_items
              SET item_status = 'confirmed', confirmed_by_shop_at = NOW()
              WHERE order_item_id = :order_item_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':order_item_id', $order_item_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to confirm order');
    }

    // Add to order status history
    $query = "INSERT INTO order_status_history
              (order_id, order_item_id, status_from, status_to, changed_by, change_note)
              VALUES (:order_id, :order_item_id, 'pending', 'confirmed', :changed_by, 'Confirmed by shop owner')";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':order_id', $order_item['order_id']);
    $stmt->bindParam(':order_item_id', $order_item_id);
    $stmt->bindParam(':changed_by', $user_id);
    $stmt->execute();

    // Check if all items in the order are confirmed
    $query = "SELECT COUNT(*) as pending_count
              FROM order_items
              WHERE order_id = :order_id AND item_status = 'pending'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':order_id', $order_item['order_id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // If all items confirmed, update main order status
    if ($result['pending_count'] == 0) {
        $query = "UPDATE orders SET order_status = 'confirmed', confirmed_at = NOW()
                  WHERE order_id = :order_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':order_id', $order_item['order_id']);
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Order confirmed successfully'
    ]);

} catch (Exception $e) {
    // Rollback on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

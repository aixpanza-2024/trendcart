<?php
/**
 * Shop Orders API
 * Returns orders for the current shop
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (($_SESSION['user_type'] ?? '') !== 'shop') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get shop_id
    $stmt = $conn->prepare("SELECT shop_id FROM shops WHERE user_id = :uid LIMIT 1");
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    $shop_id = $shop['shop_id'];

    $where = ["oi.shop_id = :shop_id"];
    $params = [':shop_id' => $shop_id];

    if (!empty($_GET['status'])) {
        $where[] = "oi.item_status = :status";
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(o.order_number LIKE :search OR u.full_name LIKE :search2)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
    }

    $where_clause = implode(' AND ', $where);

    $stmt = $conn->prepare("
        SELECT
            oi.order_item_id,
            oi.order_id,
            oi.item_status,
            oi.selected_size,
            oi.quantity,
            oi.price,
            oi.subtotal,
            o.order_number,
            o.order_date,
            o.payment_status,
            o.shipping_address,
            o.shipping_city,
            o.shipping_state,
            o.shipping_pincode,
            o.shipping_phone,
            u.full_name as customer_name,
            u.phone as customer_phone,
            p.product_name,
            p.product_id,
            pi.image_url as product_image
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        INNER JOIN users u ON o.customer_id = u.user_id
        INNER JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
        WHERE $where_clause
        ORDER BY o.order_date DESC
        LIMIT 100
    ");

    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $orders]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

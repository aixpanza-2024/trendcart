<?php
/**
 * Shop - Reports API
 * Returns shop's orders filtered by date range and status
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'shop') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid shop session']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Resolve actual shop_id from session user_id
    $sr = $conn->prepare("SELECT shop_id FROM shops WHERE user_id = :uid LIMIT 1");
    $sr->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $sr->execute();
    $shopRow = $sr->fetch(PDO::FETCH_ASSOC);
    if (!$shopRow) {
        echo json_encode(['success' => false, 'message' => 'Shop not found']);
        exit();
    }
    $shop_id = (int)$shopRow['shop_id'];

    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
    $status    = isset($_GET['status'])    ? trim($_GET['status'])    : '';

    // Distinct orders that contain items from this shop
    $sql = "SELECT o.order_id, o.order_number, o.order_date, o.order_status,
                   o.shipping_name, o.shipping_city, o.payment_method,
                   SUM(oi.subtotal)        AS shop_subtotal,
                   COUNT(oi.order_item_id) AS item_count
            FROM orders o
            INNER JOIN order_items oi ON o.order_id = oi.order_id AND oi.shop_id = :shop_id
            WHERE 1=1";

    $params = [':shop_id' => $shop_id];

    if ($date_from !== '') {
        $sql .= " AND DATE(o.order_date) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    if ($date_to !== '') {
        $sql .= " AND DATE(o.order_date) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    if ($status !== '') {
        $sql .= " AND o.order_status = :status";
        $params[':status'] = $status;
    }

    $sql .= " GROUP BY o.order_id ORDER BY o.order_date DESC LIMIT 500";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        if ($k === ':shop_id') {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($k, $v);
        }
    }
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_revenue = array_sum(array_column($orders, 'shop_subtotal'));

    echo json_encode([
        'success'       => true,
        'data'          => $orders,
        'total_orders'  => count($orders),
        'total_revenue' => $total_revenue
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

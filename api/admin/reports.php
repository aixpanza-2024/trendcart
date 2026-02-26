<?php
/**
 * Admin - Reports API
 * Returns orders (with items) filtered by date range, status, shop
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
    $status    = isset($_GET['status'])    ? trim($_GET['status'])    : '';
    $shop_id   = isset($_GET['shop_id'])   ? (int)$_GET['shop_id']   : 0;

    // Base query — one row per order
    $sql = "SELECT o.order_id, o.order_number, o.order_date, o.order_status,
                   o.total_amount, o.subtotal, o.tax_amount, o.shipping_amount,
                   o.payment_method, o.shipping_name, o.shipping_city,
                   u.full_name AS customer_name,
                   u.email AS customer_email,
                   COUNT(oi.order_item_id) AS item_count,
                   GROUP_CONCAT(DISTINCT s.shop_name ORDER BY s.shop_name SEPARATOR ', ') AS shops
            FROM orders o
            LEFT JOIN users u ON o.customer_id = u.user_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN shops s ON oi.shop_id = s.shop_id
            WHERE 1=1";

    $params = [];

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
    if ($shop_id > 0) {
        $sql .= " AND oi.shop_id = :shop_id";
        $params[':shop_id'] = $shop_id;
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

    // Summary stats
    $total_revenue  = array_sum(array_column($orders, 'total_amount'));
    $total_orders   = count($orders);

    // Fetch shop list for filter dropdown
    $shopStmt = $conn->query("SELECT shop_id, shop_name FROM shops WHERE shop_status = 'open' ORDER BY shop_name");
    $shopList = $shopStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'data'           => $orders,
        'total_orders'   => $total_orders,
        'total_revenue'  => $total_revenue,
        'shops'          => $shopList
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

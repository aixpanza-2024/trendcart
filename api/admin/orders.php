<?php
/**
 * Admin Orders List API
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $where = ["1=1"];
    $params = [];

    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(o.order_number LIKE :search OR u.full_name LIKE :search2 OR u.phone LIKE :search3)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
        $params[':search3'] = $search;
    }

    if (!empty($_GET['status'])) {
        $where[] = "o.order_status = :status";
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['payment'])) {
        $where[] = "o.payment_status = :payment";
        $params[':payment'] = $_GET['payment'];
    }

    if (!empty($_GET['date_from'])) {
        $where[] = "DATE(o.order_date) >= :date_from";
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['date_to'])) {
        $where[] = "DATE(o.order_date) <= :date_to";
        $params[':date_to'] = $_GET['date_to'];
    }

    $where_clause = implode(' AND ', $where);

    $stmt = $conn->prepare("
        SELECT o.order_id, o.order_number, o.order_date, o.total_amount,
               o.order_status, o.payment_status, o.payment_method,
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               o.shipping_city,
               GROUP_CONCAT(DISTINCT s.shop_name SEPARATOR ', ') as shop_names
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN shops s ON oi.shop_id = s.shop_id
        WHERE $where_clause
        GROUP BY o.order_id
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

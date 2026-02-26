<?php
/**
 * Customer - My Orders API
 * Requires customer session
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to view orders']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch orders with item count and totals
    $sql = "SELECT o.order_id, o.order_number, o.total_amount, o.order_status,
                   o.payment_status, o.payment_method, o.order_date,
                   o.shipping_name, o.shipping_address, o.shipping_city,
                   COUNT(oi.order_item_id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.customer_id = :customer_id
            GROUP BY o.order_id
            ORDER BY o.order_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If an order_id is requested, fetch its items
    if (isset($_GET['order_id'])) {
        $orderId = (int)$_GET['order_id'];

        $itemSql = "SELECT oi.order_item_id, oi.quantity, oi.price, oi.item_status,
                           p.product_name, p.product_id,
                           s.shop_name,
                           (SELECT image_url FROM product_images
                            WHERE product_id = p.product_id
                            ORDER BY is_primary DESC, display_order ASC
                            LIMIT 1) AS product_image,
                           CASE WHEN pr.review_id IS NOT NULL THEN 1 ELSE 0 END AS has_reviewed,
                           pr.rating AS review_rating
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.order_id
                    INNER JOIN products p ON oi.product_id = p.product_id
                    INNER JOIN shops s ON oi.shop_id = s.shop_id
                    LEFT JOIN product_reviews pr
                           ON pr.product_id = p.product_id
                          AND pr.user_id    = :customer_id_r
                          AND pr.order_id   = oi.order_id
                    WHERE oi.order_id = :order_id AND o.customer_id = :customer_id";

        $iStmt = $conn->prepare($itemSql);
        $iStmt->bindValue(':order_id',     $orderId,     PDO::PARAM_INT);
        $iStmt->bindValue(':customer_id',  $customer_id, PDO::PARAM_INT);
        $iStmt->bindValue(':customer_id_r',$customer_id, PDO::PARAM_INT);
        $iStmt->execute();
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $items]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $orders, 'count' => count($orders)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

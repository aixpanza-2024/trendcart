<?php
/**
 * Customer - My Orders API
 * Requires customer session
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login to view orders');

try {
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

        // Name, SKU and image come from the order line's own snapshot, so a
        // line stays visible (product_id NULL) after its product is deleted.
        $itemSql = "SELECT oi.order_item_id, oi.quantity, oi.price, oi.subtotal,
                           oi.selected_size, oi.selected_color, oi.item_status,
                           oi.product_name, oi.product_id, oi.product_code,
                           s.shop_name,
                           COALESCE(oi.product_image,
                               (SELECT image_url FROM product_images
                                WHERE product_id = oi.product_id
                                ORDER BY is_primary DESC, display_order ASC
                                LIMIT 1)) AS product_image,
                           CASE WHEN pr.review_id IS NOT NULL THEN 1 ELSE 0 END AS has_reviewed,
                           pr.rating AS review_rating
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.order_id
                    LEFT JOIN shops s ON oi.shop_id = s.shop_id
                    LEFT JOIN product_reviews pr
                           ON pr.user_id  = :customer_id_r
                          AND pr.order_id = oi.order_id
                          AND (pr.product_id = oi.product_id
                               OR (oi.product_id IS NULL AND pr.product_id IS NULL
                                   AND pr.product_name = oi.product_name))
                    WHERE oi.order_id = :order_id AND o.customer_id = :customer_id
                    ORDER BY oi.order_item_id ASC";

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

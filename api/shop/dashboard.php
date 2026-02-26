<?php
/**
 * Shop Dashboard API
 * Returns shop statistics and recent orders
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

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

$user_id = $_SESSION['user_id'];

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get shop information
    $query = "SELECT * FROM shops WHERE user_id = :user_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        // Shop not found, create basic shop entry
        $query = "INSERT INTO shops (user_id, shop_name, shop_status)
                  SELECT :user_id, shop_name, 'open'
                  FROM shop_profiles WHERE user_id = :user_id_2";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':user_id_2', $user_id);
        $stmt->execute();

        $shop_id = $conn->lastInsertId();

        // Fetch the newly created shop
        $query = "SELECT * FROM shops WHERE shop_id = :shop_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':shop_id', $shop_id);
        $stmt->execute();
        $shop = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $shop_id = $shop['shop_id'];

    // Get shop statistics
    $query = "SELECT
                COUNT(DISTINCT p.product_id) as total_products,
                COUNT(DISTINCT CASE WHEN p.product_status = 'active' THEN p.product_id END) as active_products,
                COALESCE(SUM(CASE WHEN oi.item_status = 'pending' THEN 1 ELSE 0 END), 0) as pending_orders,
                COALESCE(s.total_orders, 0) as total_orders,
                COALESCE(s.total_sales, 0) as total_sales,
                COALESCE(s.rating_average, 0) as rating_average
              FROM shops s
              LEFT JOIN products p ON s.shop_id = p.shop_id
              LEFT JOIN order_items oi ON s.shop_id = oi.shop_id
              WHERE s.shop_id = :shop_id
              GROUP BY s.shop_id";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':shop_id', $shop_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent orders (last 10)
    $query = "SELECT
                oi.order_item_id,
                oi.order_id,
                o.order_number,
                u.full_name as customer_name,
                oi.item_status,
                oi.subtotal as item_total,
                COUNT(oi2.order_item_id) as items_count,
                o.order_date
              FROM order_items oi
              INNER JOIN orders o ON oi.order_id = o.order_id
              INNER JOIN users u ON o.customer_id = u.user_id
              LEFT JOIN order_items oi2 ON o.order_id = oi2.order_id AND oi2.shop_id = :shop_id
              WHERE oi.shop_id = :shop_id_2
              GROUP BY oi.order_item_id
              ORDER BY o.order_date DESC
              LIMIT 10";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':shop_id', $shop_id);
    $stmt->bindParam(':shop_id_2', $shop_id);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Low stock products (stock <= threshold, includes out-of-stock)
    $query = "SELECT product_id, product_name, stock_quantity, low_stock_threshold,
                     (SELECT image_url FROM product_images
                      WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) AS primary_image
              FROM products p
              WHERE p.shop_id = :shop_id
                AND p.product_status = 'active'
                AND p.stock_quantity <= p.low_stock_threshold
              ORDER BY p.stock_quantity ASC
              LIMIT 15";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':shop_id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();
    $low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Dashboard data loaded successfully',
        'data' => [
            'shop'          => $shop,
            'stats'         => $stats,
            'recent_orders' => $recent_orders,
            'low_stock'     => $low_stock
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

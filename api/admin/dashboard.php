<?php
/**
 * Admin Dashboard API
 * Returns all dashboard statistics
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

// Auth check
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // === Main Stats ===
    $stats = [];

    // Total orders
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM orders");
    $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Total revenue
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE order_status NOT IN ('cancelled', 'refunded')");
    $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active shops
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM shops WHERE shop_status != 'suspended'");
    $stats['active_shops'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Total customers
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'customer'");
    $stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Pending orders count
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE order_status = 'pending'");
    $stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // === Revenue ===
    $revenue = [];

    // Today
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(order_date) = CURDATE() AND order_status NOT IN ('cancelled', 'refunded')");
    $revenue['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // This week
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND order_status NOT IN ('cancelled', 'refunded')");
    $revenue['weekly'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // This month
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND order_status NOT IN ('cancelled', 'refunded')");
    $revenue['monthly'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // === Pending Orders (recent 10) ===
    $stmt = $conn->query("
        SELECT o.order_id, o.order_number, o.total_amount, o.order_status, o.order_date,
               u.full_name as customer_name
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.user_id
        WHERE o.order_status IN ('pending', 'confirmed', 'processing')
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === Top Shops ===
    $stmt = $conn->query("
        SELECT shop_name, total_sales, shop_status
        FROM shops
        ORDER BY total_sales DESC
        LIMIT 5
    ");
    $top_shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === Recent Customers ===
    $stmt = $conn->query("
        SELECT full_name, email, created_at
        FROM users
        WHERE user_type = 'customer'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recent_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === Order Status Counts ===
    $stmt = $conn->query("
        SELECT order_status, COUNT(*) as cnt
        FROM orders
        GROUP BY order_status
    ");
    $status_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $order_status_counts = [];
    foreach ($status_rows as $row) {
        $order_status_counts[$row['order_status']] = $row['cnt'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'revenue' => $revenue,
            'pending_orders' => $pending_orders,
            'top_shops' => $top_shops,
            'recent_customers' => $recent_customers,
            'order_status_counts' => $order_status_counts
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

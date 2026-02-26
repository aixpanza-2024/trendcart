<?php
/**
 * Admin Revenue API
 * Returns revenue data by daily/weekly/monthly
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$period = $_GET['period'] ?? 'daily';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Date range based on period
    switch ($period) {
        case 'weekly':
            $date_filter = "o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'monthly':
            $date_filter = "o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        default: // daily
            $date_filter = "DATE(o.order_date) = CURDATE()";
            break;
    }

    // Summary stats
    $stmt = $conn->query("
        SELECT
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN o.order_status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as delivered_revenue,
            COALESCE(SUM(CASE WHEN o.order_status = 'cancelled' THEN o.total_amount ELSE 0 END), 0) as cancelled_revenue
        FROM orders o
        WHERE $date_filter
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Shop-wise revenue
    $stmt = $conn->query("
        SELECT
            s.shop_id, s.shop_name, s.shop_status, s.total_sales,
            COALESCE(SUM(CASE WHEN DATE(o.order_date) = CURDATE() THEN oi.subtotal ELSE 0 END), 0) as today_sales,
            COALESCE(SUM(CASE WHEN o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN oi.subtotal ELSE 0 END), 0) as weekly_sales,
            COALESCE(SUM(CASE WHEN o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN oi.subtotal ELSE 0 END), 0) as monthly_sales,
            COALESCE((SELECT SUM(payable_amount - paid_amount) FROM shop_payments WHERE shop_id = s.shop_id AND payment_status != 'paid'), 0) as total_pending
        FROM shops s
        LEFT JOIN order_items oi ON s.shop_id = oi.shop_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        GROUP BY s.shop_id
        ORDER BY s.total_sales DESC
    ");
    $shop_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue log (last 30 days)
    $stmt = $conn->query("
        SELECT
            DATE(o.order_date) as order_day,
            COUNT(*) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN o.order_status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as delivered_revenue,
            COALESCE(SUM(CASE WHEN o.order_status = 'cancelled' THEN o.total_amount ELSE 0 END), 0) as cancelled_revenue,
            COUNT(DISTINCT o.customer_id) as unique_customers
        FROM orders o
        GROUP BY DATE(o.order_date)
        ORDER BY order_day DESC
        LIMIT 30
    ");
    $revenue_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => $summary,
            'shop_revenue' => $shop_revenue,
            'revenue_log' => $revenue_log
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

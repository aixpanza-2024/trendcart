<?php
/**
 * Admin Customers List API
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

    // Stats
    $stats = [];
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'customer'");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'customer' AND is_active = 1");
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Build query
    $where = ["u.user_type = 'customer'"];
    $params = [];

    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(u.full_name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)";
        $params[':s1'] = $search;
        $params[':s2'] = $search;
        $params[':s3'] = $search;
    }

    if (!empty($_GET['status'])) {
        $where[] = "u.is_active = :active";
        $params[':active'] = $_GET['status'] === 'active' ? 1 : 0;
    }

    $where_clause = implode(' AND ', $where);

    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.email, u.phone, u.is_active, u.created_at,
               COUNT(DISTINCT o.order_id) as order_count,
               COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.user_id = o.customer_id AND o.order_status != 'cancelled'
        WHERE $where_clause
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
        LIMIT 100
    ");

    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'customers' => $customers
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

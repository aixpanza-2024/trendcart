<?php
/**
 * Admin Payments List API
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
    $stmt = $conn->query("SELECT COALESCE(SUM(payable_amount), 0) as total FROM shop_payments WHERE payment_status = 'unpaid'");
    $stats['total_unpaid'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->query("SELECT COALESCE(SUM(paid_amount), 0) as total FROM shop_payments WHERE payment_status = 'paid'");
    $stats['total_paid'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->query("SELECT COALESCE(SUM(commission_amount), 0) as total FROM shop_payments");
    $stats['total_commission'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->query("SELECT COUNT(DISTINCT shop_id) as cnt FROM shop_payments WHERE payment_status = 'unpaid'");
    $stats['pending_shops'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Filter payments
    $where = ["1=1"];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = "sp.payment_status = :status";
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['shop_id'])) {
        $where[] = "sp.shop_id = :shop_id";
        $params[':shop_id'] = $_GET['shop_id'];
    }

    $where_clause = implode(' AND ', $where);

    $stmt = $conn->prepare("
        SELECT sp.*, s.shop_name
        FROM shop_payments sp
        INNER JOIN shops s ON sp.shop_id = s.shop_id
        WHERE $where_clause
        ORDER BY sp.payment_status ASC, sp.period_end DESC
    ");

    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'payments' => $payments
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

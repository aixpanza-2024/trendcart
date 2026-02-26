<?php
/**
 * Admin Shops List API
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
        $where[] = "(s.shop_name LIKE :search OR u.email LIKE :search2)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
    }

    if (!empty($_GET['status'])) {
        $where[] = "s.shop_status = :status";
        $params[':status'] = $_GET['status'];
    }

    $where_clause = implode(' AND ', $where);

    $stmt = $conn->prepare("
        SELECT s.*, u.email, u.phone, u.full_name as owner_name
        FROM shops s
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE $where_clause
        ORDER BY s.created_at DESC
    ");

    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $shops]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

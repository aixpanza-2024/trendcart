<?php
/**
 * Admin Categories List API
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

    $stmt = $conn->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM products WHERE category_id = c.category_id) as product_count
        FROM categories c
        ORDER BY c.parent_category_id IS NULL DESC, c.parent_category_id, c.display_order, c.category_name
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $categories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

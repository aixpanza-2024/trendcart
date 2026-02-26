<?php
/**
 * Customer - Public Categories Listing API
 */

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query("SELECT category_id, category_name, parent_category_id FROM categories WHERE is_active = 1 ORDER BY parent_category_id IS NULL DESC, category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $categories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

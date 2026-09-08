<?php
/**
 * Customer - Public Categories Listing API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query("
        SELECT c.category_id, c.category_name, c.parent_category_id, c.category_image
        FROM categories c
        WHERE c.is_active = 1
          AND c.parent_category_id IS NULL
          AND EXISTS (
              SELECT 1 FROM products p
              INNER JOIN categories sc ON p.category_id = sc.category_id
              WHERE (sc.category_id = c.category_id OR sc.parent_category_id = c.category_id)
                AND p.product_status = 'active'
          )
        ORDER BY c.category_name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $categories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

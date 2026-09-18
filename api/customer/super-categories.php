<?php
/**
 * Customer - Public Super Categories Listing API (Men / Women / Kids ...)
 * Only returns active super categories that have at least one linked category.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query("
        SELECT sc.super_category_id, sc.super_category_name, sc.super_category_image
        FROM super_categories sc
        WHERE sc.is_active = 1
          AND EXISTS (SELECT 1 FROM super_category_links l WHERE l.super_category_id = sc.super_category_id)
        ORDER BY sc.display_order ASC, sc.super_category_name ASC
    ");
    $superCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $superCategories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

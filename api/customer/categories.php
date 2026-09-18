<?php
/**
 * Customer - Public Categories Listing API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Optional: restrict to categories linked to a given super category (Men/Women/Kids...).
    // Omitted or 0 = today's default behaviour (all top-level categories), unchanged.
    $super_category_id = isset($_GET['super_category_id']) ? (int)$_GET['super_category_id'] : 0;

    $sql = "
        SELECT c.category_id, c.category_name, c.parent_category_id, c.category_image
        FROM categories c
        WHERE c.is_active = 1
          AND c.parent_category_id IS NULL
          AND EXISTS (
              SELECT 1 FROM products p
              INNER JOIN categories sc ON p.category_id = sc.category_id
              WHERE (sc.category_id = c.category_id OR sc.parent_category_id = c.category_id)
                AND p.product_status = 'active'
          )";
    if ($super_category_id > 0) {
        $sql .= " AND EXISTS (
              SELECT 1 FROM super_category_links l
              WHERE l.category_id = c.category_id AND l.super_category_id = :scid
          )";
    }
    $sql .= " ORDER BY c.category_name ASC";

    $stmt = $conn->prepare($sql);
    if ($super_category_id > 0) {
        $stmt->bindValue(':scid', $super_category_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $categories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

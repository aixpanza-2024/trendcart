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

    // Dress and Cosmetics are separate storefronts — only show tabs that have at
    // least one active product from a shop of this vertical. Defaults to 'dress'.
    $vertical = in_array($_GET['vertical'] ?? '', ['dress', 'cosmetics'], true) ? $_GET['vertical'] : 'dress';

    $stmt = $conn->prepare("
        SELECT sc.super_category_id, sc.super_category_name, sc.super_category_image
        FROM super_categories sc
        WHERE sc.is_active = 1
          AND EXISTS (
              SELECT 1 FROM super_category_links l
              INNER JOIN products p ON p.category_id = l.category_id
              INNER JOIN shops s ON s.shop_id = p.shop_id
              WHERE l.super_category_id = sc.super_category_id
                AND p.product_status = 'active'
                AND s.business_vertical = :vertical
          )
        ORDER BY sc.display_order ASC, sc.super_category_name ASC
    ");
    $stmt->bindValue(':vertical', $vertical);
    $stmt->execute();
    $superCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $superCategories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

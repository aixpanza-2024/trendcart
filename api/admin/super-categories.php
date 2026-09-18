<?php
/**
 * Admin Super Categories List API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query("
        SELECT sc.*,
               (SELECT COUNT(*) FROM super_category_links l WHERE l.super_category_id = sc.super_category_id) as linked_count
        FROM super_categories sc
        ORDER BY sc.display_order ASC, sc.super_category_name ASC
    ");
    $superCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $superCategories]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

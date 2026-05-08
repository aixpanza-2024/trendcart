<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query("
        SELECT banner_id, title, subtitle, image_url, link_url
        FROM banners
        WHERE is_active = 1
        ORDER BY display_order ASC, banner_id ASC
    ");
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $banners]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

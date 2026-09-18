<?php
/**
 * Admin Create Super Category API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['super_category_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Super category name is required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO super_categories (super_category_name, super_category_image, display_order)
        VALUES (:name, :image, :display_order)
    ");
    $stmt->bindParam(':name', $data['super_category_name']);
    $image = !empty($data['super_category_image']) ? $data['super_category_image'] : null;
    $stmt->bindParam(':image', $image);
    $order = $data['display_order'] ?? 0;
    $stmt->bindParam(':display_order', $order);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Super category created', 'data' => ['super_category_id' => $conn->lastInsertId()]]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

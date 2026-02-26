<?php
/**
 * Admin Update Category API
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['category_id']) || empty($data['category_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category ID and name required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        UPDATE categories SET
            category_name = :name,
            parent_category_id = :parent,
            category_description = :desc,
            display_order = :display_order
        WHERE category_id = :id
    ");
    $stmt->bindParam(':name', $data['category_name']);
    $parent = !empty($data['parent_category_id']) ? $data['parent_category_id'] : null;
    $stmt->bindParam(':parent', $parent);
    $desc = $data['category_description'] ?? '';
    $stmt->bindParam(':desc', $desc);
    $order = $data['display_order'] ?? 0;
    $stmt->bindParam(':display_order', $order);
    $stmt->bindParam(':id', $data['category_id']);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Category updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

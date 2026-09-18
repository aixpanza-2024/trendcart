<?php
/**
 * Admin Delete Super Category API
 * Deleting a super category also removes its links (ON DELETE CASCADE) —
 * the linked parent categories and their products are never touched.
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

if (!$data || empty($data['super_category_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Super category ID required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("DELETE FROM super_categories WHERE super_category_id = :id");
    $stmt->bindParam(':id', $data['super_category_id']);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Super category deleted']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

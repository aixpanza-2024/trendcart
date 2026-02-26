<?php
/**
 * Admin Toggle Category Active/Inactive API
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

if (!$data || !isset($data['category_id']) || !isset($data['is_active'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category ID and status required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("UPDATE categories SET is_active = :active WHERE category_id = :id");
    $stmt->bindParam(':active', $data['is_active']);
    $stmt->bindParam(':id', $data['category_id']);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Category status updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

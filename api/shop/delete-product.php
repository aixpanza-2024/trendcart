<?php
/**
 * Delete Product API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_type'] !== 'shop') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['product_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = $data['product_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get shop_id
    $query = "SELECT shop_id FROM shops WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        throw new Exception('Shop not found');
    }

    // Delete product (cascade will delete images)
    $query = "DELETE FROM products WHERE product_id = :product_id AND shop_id = :shop_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->bindParam(':shop_id', $shop['shop_id']);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        throw new Exception('Failed to delete product');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

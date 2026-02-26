<?php
/**
 * Delete Product Image API
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

if (!$data || !isset($data['image_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Image ID is required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$image_id = $data['image_id'];

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

    // Verify image belongs to this shop's product
    $query = "SELECT pi.image_url, pi.product_id
              FROM product_images pi
              INNER JOIN products p ON pi.product_id = p.product_id
              WHERE pi.image_id = :image_id AND p.shop_id = :shop_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':image_id', $image_id);
    $stmt->bindParam(':shop_id', $shop['shop_id']);
    $stmt->execute();
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new Exception('Image not found or access denied');
    }

    // Delete from database
    $query = "DELETE FROM product_images WHERE image_id = :image_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':image_id', $image_id);

    if ($stmt->execute()) {
        // Try to delete physical file
        $file_path = '../../' . ltrim($image['image_url'], '/');
        if (file_exists($file_path)) {
            @unlink($file_path);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
    } else {
        throw new Exception('Failed to delete image');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

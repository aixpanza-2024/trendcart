<?php
/**
 * Get Single Product API
 * Get product details for editing
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start session
session_start();

// Include required files
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login.'
    ]);
    exit();
}

// Check if user is shop owner
if ($_SESSION['user_type'] !== 'shop') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Shop owners only.'
    ]);
    exit();
}

// Get product ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = $_GET['id'];

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get shop_id
    $query = "SELECT shop_id FROM shops WHERE user_id = :user_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        throw new Exception('Shop not found');
    }

    $shop_id = $shop['shop_id'];

    // Get product details
    $query = "SELECT p.*
              FROM products p
              WHERE p.product_id = :product_id AND p.shop_id = :shop_id
              LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->bindParam(':shop_id', $shop_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Product not found or access denied'
        ]);
        exit();
    }

    // Get product images
    $query = "SELECT image_id, image_url, is_primary
              FROM product_images
              WHERE product_id = :product_id
              ORDER BY is_primary DESC, display_order";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get size variants
    $szQuery = "SELECT size_id, size_label, stock_quantity, price_adjustment, display_order
                FROM product_sizes
                WHERE product_id = :product_id
                ORDER BY display_order ASC, size_id ASC";
    $szStmt = $conn->prepare($szQuery);
    $szStmt->bindValue(':product_id', $product_id, PDO::PARAM_INT);
    $szStmt->execute();
    $product['sizes'] = $szStmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Product loaded successfully',
        'data' => $product
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

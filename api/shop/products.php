<?php
/**
 * Products List API
 * Get all products for current shop with filters
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

$user_id = $_SESSION['user_id'];

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

    // Build query with filters
    $where_conditions = ["p.shop_id = :shop_id"];
    $params = [':shop_id' => $shop_id];

    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where_conditions[] = "(p.product_name LIKE :search OR p.product_code LIKE :search2)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
    }

    // Status filter
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where_conditions[] = "p.product_status = :status";
        $params[':status'] = $_GET['status'];
    }

    // Category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $where_conditions[] = "p.category_id = :category";
        $params[':category'] = $_GET['category'];
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get products with primary image
    $query = "SELECT
                p.*,
                c.category_name,
                (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.category_id
              WHERE $where_clause
              ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Products loaded successfully',
        'data' => $products
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

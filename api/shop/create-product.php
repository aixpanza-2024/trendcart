<?php
/**
 * Create Product API
 * Create a new product with images
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

    // Get POST data
    $product_name = $_POST['product_name'] ?? '';
    $product_code = $_POST['product_code'] ?? null;
    $product_description = $_POST['product_description'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $product_status = $_POST['product_status'] ?? 'active';

    $price = $_POST['price'] ?? 0;
    $original_price = $_POST['original_price'] ?? null;
    $discount_percentage = $_POST['discount_percentage'] ?? 0;
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    $low_stock_threshold = $_POST['low_stock_threshold'] ?? 10;

    $color = $_POST['color'] ?? null;
    $material = $_POST['material'] ?? null;
    $sizes_json = $_POST['sizes_json'] ?? '[]';
    $fabric_type = $_POST['fabric_type'] ?? null;
    $pattern = $_POST['pattern'] ?? null;
    $weight = $_POST['weight'] ?? null;
    $length = $_POST['length'] ?? null;
    $width = $_POST['width'] ?? null;

    // Validate required fields
    if (empty($product_name)) {
        throw new Exception('Product name is required');
    }

    if (empty($price) || $price <= 0) {
        throw new Exception('Valid price is required');
    }

    // Begin transaction
    $conn->beginTransaction();

    // Parse sizes
    $sizes = json_decode($sizes_json, true);
    if (!is_array($sizes)) $sizes = [];
    // Build comma-joined size label for the legacy `size` column (keeps search/filter working)
    $size_summary = implode(',', array_map(fn($s) => $s['size_label'], $sizes));
    // Total stock = sum of variant stocks (or the entered stock_quantity if no variants)
    $total_stock = count($sizes) > 0
        ? array_sum(array_column($sizes, 'stock_quantity'))
        : (int)$stock_quantity;

    // Insert product
    $query = "INSERT INTO products (
                shop_id, category_id, product_name, product_description, product_code,
                price, original_price, discount_percentage,
                stock_quantity, low_stock_threshold,
                color, size, material, fabric_type, pattern,
                length, width, weight, product_status
              ) VALUES (
                :shop_id, :category_id, :product_name, :product_description, :product_code,
                :price, :original_price, :discount_percentage,
                :stock_quantity, :low_stock_threshold,
                :color, :size, :material, :fabric_type, :pattern,
                :length, :width, :weight, :product_status
              )";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':shop_id', $shop_id);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':product_name', $product_name);
    $stmt->bindParam(':product_description', $product_description);
    $stmt->bindParam(':product_code', $product_code);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':original_price', $original_price);
    $stmt->bindParam(':discount_percentage', $discount_percentage);
    $stmt->bindParam(':stock_quantity', $total_stock);
    $stmt->bindParam(':low_stock_threshold', $low_stock_threshold);
    $stmt->bindParam(':color', $color);
    $stmt->bindParam(':size', $size_summary);
    $stmt->bindParam(':material', $material);
    $stmt->bindParam(':fabric_type', $fabric_type);
    $stmt->bindParam(':pattern', $pattern);
    $stmt->bindParam(':length', $length);
    $stmt->bindParam(':width', $width);
    $stmt->bindParam(':weight', $weight);
    $stmt->bindParam(':product_status', $product_status);

    if (!$stmt->execute()) {
        throw new Exception('Failed to create product');
    }

    $product_id = $conn->lastInsertId();

    // Insert size variants
    if (!empty($sizes)) {
        $sizeStmt = $conn->prepare(
            "INSERT INTO product_sizes (product_id, size_label, stock_quantity, price_adjustment, display_order)
             VALUES (:product_id, :size_label, :stock_qty, :price_adj, :disp_order)"
        );
        foreach ($sizes as $idx => $sv) {
            $sizeStmt->bindValue(':product_id',  $product_id, PDO::PARAM_INT);
            $sizeStmt->bindValue(':size_label',  trim($sv['size_label']));
            $sizeStmt->bindValue(':stock_qty',   max(0, (int)($sv['stock_quantity'] ?? 0)), PDO::PARAM_INT);
            $sizeStmt->bindValue(':price_adj',   round((float)($sv['price_adjustment'] ?? 0), 2));
            $sizeStmt->bindValue(':disp_order',  $idx, PDO::PARAM_INT);
            $sizeStmt->execute();
        }
    }

    // Handle image uploads
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = '../../uploads/products/';

        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $is_primary_flags = $_POST['is_primary'] ?? [];

        foreach ($_FILES['images']['tmp_name'] as $index => $tmp_name) {
            if (empty($tmp_name)) continue;

            $file_name = $_FILES['images']['name'][$index];
            $file_size = $_FILES['images']['size'][$index];
            $file_error = $_FILES['images']['error'][$index];

            // Validate file
            if ($file_error !== UPLOAD_ERR_OK) {
                continue;
            }

            // Check file size (max 5MB)
            if ($file_size > 5 * 1024 * 1024) {
                continue;
            }

            // Generate unique filename
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_name = uniqid('product_' . $product_id . '_') . '.' . $extension;
            $file_path = $upload_dir . $unique_name;

            // Move uploaded file
            if (move_uploaded_file($tmp_name, $file_path)) {
                $image_url = '/uploads/products/' . $unique_name;
                $is_primary = isset($is_primary_flags[$index]) && $is_primary_flags[$index] == '1' ? 1 : 0;

                // Insert image record
                $query = "INSERT INTO product_images (product_id, image_url, is_primary, display_order)
                          VALUES (:product_id, :image_url, :is_primary, :display_order)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':product_id', $product_id);
                $stmt->bindParam(':image_url', $image_url);
                $stmt->bindParam(':is_primary', $is_primary);
                $stmt->bindParam(':display_order', $index);
                $stmt->execute();
            }
        }
    }

    // Commit transaction
    $conn->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Product created successfully',
        'data' => [
            'product_id' => $product_id
        ]
    ]);

} catch (Exception $e) {
    // Rollback on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

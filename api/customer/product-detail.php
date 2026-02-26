<?php
/**
 * Customer - Public Product Detail API
 * Returns full product info + all images + shop info
 */

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($product_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        exit;
    }

    // Product + shop + category
    $stmt = $conn->prepare("
        SELECT p.product_id, p.product_name, p.price, p.original_price,
               p.discount_percentage, p.product_description, p.orders_count,
               p.created_at, p.is_featured,
               s.shop_id, s.shop_name, s.shop_description, s.shop_logo,
               s.shop_city, s.rating_average, s.total_ratings, s.total_products,
               c.category_name
        FROM products p
        INNER JOIN shops s ON p.shop_id = s.shop_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE p.product_id = :id
          AND p.product_status = 'active'
          AND s.shop_status = 'open'
        LIMIT 1
    ");
    $stmt->bindValue(':id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // All images (primary first)
    $imgStmt = $conn->prepare("
        SELECT image_id, image_url, is_primary
        FROM product_images
        WHERE product_id = :id
        ORDER BY is_primary DESC, display_order ASC
    ");
    $imgStmt->bindValue(':id', $product_id, PDO::PARAM_INT);
    $imgStmt->execute();
    $product['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Size variants (graceful: returns [] if table not yet created)
    try {
        $szStmt = $conn->prepare("
            SELECT size_label, stock_quantity, price_adjustment, display_order
            FROM product_sizes
            WHERE product_id = :id
            ORDER BY display_order ASC, size_id ASC
        ");
        $szStmt->bindValue(':id', $product_id, PDO::PARAM_INT);
        $szStmt->execute();
        $product['sizes'] = $szStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $szEx) {
        $product['sizes'] = [];
    }

    // Latest approved reviews (up to 10)
    $revStmt = $conn->prepare("
        SELECT pr.rating, pr.review_text, pr.created_at,
               u.full_name AS reviewer_name
        FROM product_reviews pr
        INNER JOIN users u ON pr.user_id = u.user_id
        WHERE pr.product_id = :id AND pr.is_approved = 1
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $revStmt->bindValue(':id', $product_id, PDO::PARAM_INT);
    $revStmt->execute();
    $product['reviews'] = $revStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $product]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

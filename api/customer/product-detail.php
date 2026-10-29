<?php
/**
 * Customer - Public Product Detail API
 * Returns full product info + all images + shop info
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/CustomerLocation.php';
require_once __DIR__ . '/../utils/TrendingProducts.php';

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
               p.color, p.material, p.fabric_type, p.pattern,
               s.shop_id, s.shop_name, s.shop_description, s.shop_logo,
               s.shop_city, s.rating_average, s.total_ratings, s.total_products,
               s.latitude AS shop_lat, s.longitude AS shop_lng,
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

    // Counts towards the homepage "Trending Now" row
    TrendingProducts::recordView($conn, $product_id);

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

    // How far the shop is from the customer's chosen location (?lat=&lng=,
    // or their saved location); shop coordinates are not sent out
    $product['shop_distance_km'] = CustomerLocation::kmTo(CustomerLocation::current($conn), $product['shop_lat'], $product['shop_lng']);
    unset($product['shop_lat'], $product['shop_lng']);

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

    // Color variants (graceful: returns [] if table not yet created)
    try {
        $clStmt = $conn->prepare("
            SELECT color_name, display_order
            FROM product_colors
            WHERE product_id = :id
            ORDER BY display_order ASC, color_id ASC
        ");
        $clStmt->bindValue(':id', $product_id, PDO::PARAM_INT);
        $clStmt->execute();
        $product['colors'] = $clStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $clEx) {
        $product['colors'] = [];
    }

    // Colour/size variants. sizes_by_color / color_stock are only set when
    // the stock is split by colour: picking a colour then shows just the
    // sizes that exist in it. Without them the page works as before.
    try {
        require_once __DIR__ . '/../utils/ProductVariants.php';
        $variants = ProductVariants::forProduct($conn, $product_id);
        $product['variants'] = array_map(fn($v) => [
            'variant_id'       => (int)$v['variant_id'],
            'color_name'       => $v['color_name'],
            'size_label'       => $v['size_label'],
            'stock_quantity'   => (int)$v['stock_quantity'],
            'price_adjustment' => $v['price_adjustment'],
        ], $variants);
        $product += ProductVariants::optionsForPage($variants);
    } catch (Exception $vEx) {
        $product['variants'] = [];
    }

    // Customer reviews are for the admin and shop owners only — customers and
    // guests get no reviews (show_reviews tells the page to leave the section hidden)
    $product['show_reviews'] = isset($_SESSION['logged_in']) && in_array($_SESSION['user_type'] ?? '', ['admin', 'shop'], true);
    $product['reviews'] = [];
    if ($product['show_reviews']) {
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
    }

    echo json_encode(['success' => true, 'data' => $product]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

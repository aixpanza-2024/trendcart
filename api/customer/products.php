<?php
/**
 * Customer - Public Products Listing API
 * Supports filtering by shop, category, price range, sort order, search
 */

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $shop_id     = isset($_GET['shop_id'])     ? (int)$_GET['shop_id']       : 0;
    $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id']   : 0;
    $max_price   = isset($_GET['max_price'])   ? (float)$_GET['max_price']   : 0;
    $sort        = isset($_GET['sort'])        ? trim($_GET['sort'])          : 'featured';
    $search      = isset($_GET['search'])      ? trim($_GET['search'])        : '';

    // Check whether `size` column exists in products table (may be absent on older DBs)
    $size_expr = "'' AS size";
    try {
        $conn->query("SELECT `size` FROM `products` LIMIT 0");
        $size_expr = 'p.size';
    } catch (\PDOException $e) { /* column absent – fall through to empty string */ }

    $sql = "SELECT p.product_id, p.product_name, p.price, p.original_price,
                   p.discount_percentage, p.product_description,
                   p.category_id, p.created_at, p.orders_count,
                   $size_expr,
                   s.shop_id, s.shop_name,
                   c.category_name,
                   (SELECT image_url FROM product_images
                    WHERE product_id = p.product_id
                    ORDER BY is_primary DESC, display_order ASC
                    LIMIT 1) AS primary_image
            FROM products p
            INNER JOIN shops s ON p.shop_id = s.shop_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.product_status = 'active' AND s.shop_status = 'open' AND p.stock_quantity > 0";

    $params = [];

    if ($shop_id > 0) {
        $sql .= " AND p.shop_id = :shop_id";
        $params[':shop_id'] = $shop_id;
    }
    if ($category_id > 0) {
        $sql .= " AND p.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }
    if ($max_price > 0) {
        $sql .= " AND p.price <= :max_price";
        $params[':max_price'] = $max_price;
    }
    if ($search !== '') {
        $sql .= " AND (p.product_name LIKE :search OR p.product_description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    switch ($sort) {
        case 'price-low':  $sql .= " ORDER BY p.price ASC";             break;
        case 'price-high': $sql .= " ORDER BY p.price DESC";            break;
        case 'newest':     $sql .= " ORDER BY p.created_at DESC";       break;
        case 'popular':    $sql .= " ORDER BY p.orders_count DESC";     break;
        default:           $sql .= " ORDER BY p.is_featured DESC, p.created_at DESC";
    }

    $sql .= " LIMIT 60";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also fetch shop info if shop_id provided
    $shop_info = null;
    if ($shop_id > 0) {
        $sStmt = $conn->prepare("SELECT shop_id, shop_name, shop_description, shop_logo, shop_city, rating_average, total_ratings, total_products FROM shops WHERE shop_id = :id LIMIT 1");
        $sStmt->bindValue(':id', $shop_id, PDO::PARAM_INT);
        $sStmt->execute();
        $shop_info = $sStmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success'   => true,
        'data'      => $products,
        'count'     => count($products),
        'shop_info' => $shop_info
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

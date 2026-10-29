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

    $shop_id           = isset($_GET['shop_id'])           ? (int)$_GET['shop_id']           : 0;
    $category_id       = isset($_GET['category_id'])       ? (int)$_GET['category_id']       : 0;
    $super_category_id = isset($_GET['super_category_id']) ? (int)$_GET['super_category_id'] : 0;
    $on_offer    = isset($_GET['on_offer']) && $_GET['on_offer'] == '1';
    $max_price   = isset($_GET['max_price'])   ? (float)$_GET['max_price']   : 0;
    $sort        = isset($_GET['sort'])        ? trim($_GET['sort'])          : 'featured';
    $search      = isset($_GET['search'])      ? trim($_GET['search'])        : '';
    // Dress and Cosmetics are separate storefronts. A specific shop_id already
    // disambiguates on its own, so the vertical filter only applies when
    // browsing broadly (no shop_id) — defaults to 'dress'.
    $vertical    = in_array($_GET['vertical'] ?? '', ['dress', 'cosmetics'], true) ? $_GET['vertical'] : 'dress';

    // Check whether `size` column exists in products table (may be absent on older DBs)
    $size_expr = "'' AS size";
    try {
        $conn->query("SELECT `size` FROM `products` LIMIT 0");
        $size_expr = 'p.size';
    } catch (\PDOException $e) { /* column absent – fall through to empty string */ }

    // Per-size stock from product_sizes table (if it exists)
    $sizes_stock_expr = "NULL AS sizes_stock";
    try {
        $conn->query("SELECT 1 FROM `product_sizes` LIMIT 0");
        $sizes_stock_expr = "(SELECT GROUP_CONCAT(CONCAT(ps.size_label,':',ps.stock_quantity) ORDER BY ps.display_order SEPARATOR ',') FROM product_sizes ps WHERE ps.product_id = p.product_id) AS sizes_stock";
    } catch (\PDOException $e) { /* table absent */ }

    // Colours for the card's colour buttons (same list as the product page), "Red|Blue".
    // variants_stock ("Red|M:5,Red|L:0") is only set when stock is split by colour,
    // so picking a colour on the card shows which sizes are left in it.
    $colors_expr = "NULL AS colors";
    $variants_stock_expr = "NULL AS variants_stock";
    try {
        $conn->query("SELECT 1 FROM `product_colors` LIMIT 0");
        $colors_expr = "(SELECT GROUP_CONCAT(pc.color_name ORDER BY pc.display_order, pc.color_id SEPARATOR '|') FROM product_colors pc WHERE pc.product_id = p.product_id) AS colors";
    } catch (\PDOException $e) { /* table absent */ }
    try {
        $conn->query("SELECT 1 FROM `product_variants` LIMIT 0");
        $variants_stock_expr = "(SELECT GROUP_CONCAT(CONCAT(pv.color_name,'|',pv.size_label,':',pv.stock_quantity) ORDER BY pv.display_order SEPARATOR ',') FROM product_variants pv WHERE pv.product_id = p.product_id AND pv.color_name <> '') AS variants_stock";
    } catch (\PDOException $e) { /* table absent */ }

    $sql = "SELECT p.product_id, p.product_name, p.price, p.original_price,
                   p.discount_percentage, p.product_description,
                   p.category_id, p.created_at, p.orders_count,
                   $size_expr, $sizes_stock_expr, $colors_expr, $variants_stock_expr,
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
    } else {
        $sql .= " AND s.business_vertical = :vertical";
        $params[':vertical'] = $vertical;
    }
    if ($category_id > 0) {
        // The category itself or one of its subcategories (e.g. "Sarees" includes
        // "Silk Sarees") — the same rule categories.php uses to list it.
        $sql .= " AND (p.category_id = :category_id
                       OR p.category_id IN (SELECT category_id FROM categories WHERE parent_category_id = :category_id_parent))";
        $params[':category_id'] = $category_id;
        $params[':category_id_parent'] = $category_id;
    }
    if ($super_category_id > 0) {
        // A product qualifies if its own category is linked to this super category,
        // or its category's PARENT is linked (covers products filed under a subcategory
        // like "Lipstick" whose parent "Makeup" is what's actually linked to "Cosmetics").
        $sql .= " AND EXISTS (
                    SELECT 1 FROM super_category_links l
                    WHERE l.super_category_id = :super_category_id
                      AND l.category_id IN (
                          p.category_id,
                          (SELECT parent_category_id FROM categories WHERE category_id = p.category_id)
                      )
                  )";
        $params[':super_category_id'] = $super_category_id;
    }
    if ($on_offer) {
        $sql .= " AND p.discount_percentage > 0";
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

    $sql .= " LIMIT 500";

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

    // Also fetch super-category info (name) if super_category_id provided, for the page header
    $super_category_info = null;
    if ($super_category_id > 0) {
        $scStmt = $conn->prepare("SELECT super_category_id, super_category_name, super_category_image FROM super_categories WHERE super_category_id = :id LIMIT 1");
        $scStmt->bindValue(':id', $super_category_id, PDO::PARAM_INT);
        $scStmt->execute();
        $super_category_info = $scStmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success'             => true,
        'data'                => $products,
        'count'               => count($products),
        'shop_info'           => $shop_info,
        'super_category_info' => $super_category_info,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

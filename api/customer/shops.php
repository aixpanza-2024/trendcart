<?php
/**
 * Customer - Public Shops Listing API
 * Returns aggregated product data per shop for client-side filtering.
 */

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 100) : 20;

    $sql = "SELECT s.shop_id, s.shop_name, s.shop_description, s.shop_logo,
                   s.shop_city, s.rating_average, s.total_ratings, s.total_products,
                   s.shop_status, s.created_at,
                   MIN(p.price) AS min_price,
                   MAX(CASE WHEN p.discount_percentage > 0 THEN 1 ELSE 0 END) AS has_offers,
                   COALESCE(SUM(p.orders_count), 0) AS total_orders,
                   GROUP_CONCAT(DISTINCT p.category_id ORDER BY p.category_id SEPARATOR ',') AS category_ids
            FROM shops s
            LEFT JOIN products p ON p.shop_id = s.shop_id AND p.product_status = 'active'
            WHERE s.shop_status = 'open'";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (s.shop_name LIKE :search OR s.shop_description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " GROUP BY s.shop_id
              ORDER BY s.total_products DESC, s.rating_average DESC
              LIMIT :limit";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $shops, 'count' => count($shops)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

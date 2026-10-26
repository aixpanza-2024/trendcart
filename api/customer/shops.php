<?php
/**
 * Customer - Public Shops Listing API
 * Returns aggregated product data per shop for client-side filtering.
 * Optional ?lat=&lng= (navbar location chooser) adds distance_km per shop;
 * a logged-in customer's saved location is used when they are not sent.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/CustomerLocation.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 100) : 20;
    // Dress and Cosmetics are separate storefronts — defaults to 'dress'.
    $vertical = in_array($_GET['vertical'] ?? '', ['dress', 'cosmetics'], true) ? $_GET['vertical'] : 'dress';

    $sql = "SELECT s.shop_id, s.shop_name, s.business_vertical, s.shop_description, s.shop_logo,
                   s.shop_city, s.rating_average, s.total_ratings, s.total_products,
                   s.shop_status, s.created_at, s.latitude, s.longitude,
                   MIN(p.price) AS min_price,
                   MAX(CASE WHEN p.discount_percentage > 0 THEN 1 ELSE 0 END) AS has_offers,
                   COALESCE(SUM(p.orders_count), 0) AS total_orders,
                   GROUP_CONCAT(DISTINCT p.category_id ORDER BY p.category_id SEPARATOR ',') AS category_ids
            FROM shops s
            LEFT JOIN products p ON p.shop_id = s.shop_id AND p.product_status = 'active'
            WHERE s.shop_status = 'open' AND s.business_vertical = :vertical";

    $params = [':vertical' => $vertical];

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

    // Distance from the customer's chosen location; the shop's exact
    // coordinates stay on the server
    $from = CustomerLocation::current($conn);
    foreach ($shops as &$shop) {
        $shop['distance_km'] = CustomerLocation::kmTo($from, $shop['latitude'], $shop['longitude']);
        unset($shop['latitude'], $shop['longitude']);
    }
    unset($shop);

    echo json_encode(['success' => true, 'data' => $shops, 'count' => count($shops)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

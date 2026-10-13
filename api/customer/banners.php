<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

/**
 * Derives a structured (target_type, target_id) pair from a banner's web
 * link_url, so the app can deep-link natively instead of opening a webview.
 * Admin still only enters one link_url — nothing new to configure.
 */
function parseBannerTarget($link_url) {
    if (!$link_url) return ['type' => null, 'id' => null];

    $path = parse_url($link_url, PHP_URL_PATH) ?? '';
    parse_str(parse_url($link_url, PHP_URL_QUERY) ?? '', $query);

    if (strpos($path, 'product-detail.html') !== false && isset($query['id'])) {
        return ['type' => 'product', 'id' => (int)$query['id']];
    }
    if (strpos($path, 'products.html') !== false && isset($query['shop_id'])) {
        return ['type' => 'shop', 'id' => (int)$query['shop_id']];
    }
    if (strpos($path, 'products.html') !== false && isset($query['category_id'])) {
        return ['type' => 'category', 'id' => (int)$query['category_id']];
    }
    if (strpos($path, 'coupons.html') !== false) {
        return ['type' => 'coupons', 'id' => null];
    }
    return ['type' => 'url', 'id' => null];
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query("
        SELECT banner_id, title, subtitle, image_url, link_url
        FROM banners
        WHERE is_active = 1
        ORDER BY display_order ASC, banner_id ASC
    ");
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($banners as &$banner) {
        $target = parseBannerTarget($banner['link_url']);
        $banner['target_type'] = $target['type'];
        $banner['target_id']   = $target['id'];
    }
    unset($banner);

    echo json_encode(['success' => true, 'data' => $banners]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

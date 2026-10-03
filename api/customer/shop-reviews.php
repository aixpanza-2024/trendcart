<?php
/**
 * Customer - Shop Reviews
 * GET ?shop_id=X  -> every approved review for that shop, newest first.
 * Public (no login required — same as browsing a shop's products).
 *
 * Reads product_reviews.shop_id/product_name directly (not via a join to
 * products), so a review for a since-deleted product still shows up here —
 * product_id/product_image are null for those, which the UI shows plainly
 * as "(product no longer available)" rather than hiding the review.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$shop_id = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;
if ($shop_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'shop_id is required']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare(
        "SELECT pr.review_id, pr.rating, pr.review_text, pr.product_name, pr.product_id,
                pr.created_at, u.full_name AS reviewer_name,
                (SELECT image_url FROM product_images
                 WHERE product_id = pr.product_id
                 ORDER BY is_primary DESC, display_order ASC
                 LIMIT 1) AS product_image
         FROM product_reviews pr
         INNER JOIN users u ON pr.user_id = u.user_id
         WHERE pr.shop_id = :shop_id AND pr.is_approved = 1
         ORDER BY pr.created_at DESC
         LIMIT 200"
    );
    $stmt->bindValue(':shop_id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $shopStmt = $conn->prepare("SELECT rating_average, total_ratings FROM shops WHERE shop_id = :id LIMIT 1");
    $shopStmt->bindValue(':id', $shop_id, PDO::PARAM_INT);
    $shopStmt->execute();
    $shop = $shopStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'data'           => $reviews,
        'count'          => count($reviews),
        'rating_average' => $shop ? (float)$shop['rating_average'] : 0,
        'total_ratings'  => $shop ? (int)$shop['total_ratings']   : 0,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * Customer - Wishlist
 * GET    : list the logged-in customer's wishlisted products, with live product data
 * POST   : { product_id } — add (idempotent; already-wishlisted is still success)
 * DELETE : { product_id } — remove
 * Requires customer session/token. POST/DELETE are the Flutter app's contract;
 * the web widget still uses the separate wishlist-toggle.php.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

$database = new Database();
$conn = $database->getConnection();

$user_id = TokenAuth::requireCustomer($conn, 'Please login to use your wishlist');

if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = (int)($data['product_id'] ?? 0);

    if ($product_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'product_id is required']);
        exit;
    }

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $prod = $conn->prepare("SELECT product_id FROM products WHERE product_id = :pid LIMIT 1");
            $prod->bindValue(':pid', $product_id, PDO::PARAM_INT);
            $prod->execute();
            if (!$prod->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit;
            }

            $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (:uid, :pid)")
                 ->execute([':uid' => $user_id, ':pid' => $product_id]);
            echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
        } else {
            $conn->prepare("DELETE FROM wishlist WHERE user_id = :uid AND product_id = :pid")
                 ->execute([':uid' => $user_id, ':pid' => $product_id]);
            echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT w.product_id, w.added_at,
                p.product_name, p.price, p.original_price, p.discount_percentage,
                p.product_status, p.stock_quantity,
                s.shop_id, s.shop_name, s.shop_status,
                (SELECT image_url FROM product_images
                 WHERE product_id = p.product_id
                 ORDER BY is_primary DESC, display_order ASC
                 LIMIT 1) AS primary_image
         FROM wishlist w
         INNER JOIN products p ON w.product_id = p.product_id
         INNER JOIN shops s ON p.shop_id = s.shop_id
         WHERE w.user_id = :uid
         ORDER BY w.added_at DESC"
    );
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Flag items whose product/shop is no longer live, so the app can grey them out
    // instead of silently dropping them from the list
    foreach ($rows as &$r) {
        $r['is_available'] = ($r['product_status'] === 'active' && $r['shop_status'] === 'open' && (int)$r['stock_quantity'] > 0);
    }

    echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

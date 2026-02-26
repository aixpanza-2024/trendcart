<?php
/**
 * Customer - Submit Product Review
 * POST only. Requires customer session.
 * Rating allowed only when order item status = 'delivered'.
 * One review per product per order (enforced by DB unique key + app check).
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

// Accept JSON or form-encoded body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) $body = $_POST;

$order_item_id = isset($body['order_item_id']) ? (int)$body['order_item_id'] : 0;
$rating        = isset($body['rating'])        ? (int)$body['rating']        : 0;
$review_text   = isset($body['review_text'])   ? trim($body['review_text'])  : '';

if ($order_item_id <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid order item and rating (1–5) are required']);
    exit;
}

require_once '../config/database.php';

try {
    $database = new Database();
    $conn     = $database->getConnection();

    // Verify item belongs to this customer and is delivered
    $stmt = $conn->prepare("
        SELECT oi.product_id, oi.order_id, oi.item_status
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.order_item_id = :item_id AND o.customer_id = :customer_id
        LIMIT 1
    ");
    $stmt->bindValue(':item_id',     $order_item_id, PDO::PARAM_INT);
    $stmt->bindValue(':customer_id', $customer_id,   PDO::PARAM_INT);
    $stmt->execute();
    $item = $stmt->fetch();

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order item not found']);
        exit;
    }

    if ($item['item_status'] !== 'delivered') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only rate a product after it is delivered']);
        exit;
    }

    $product_id = (int)$item['product_id'];
    $order_id   = (int)$item['order_id'];

    // Check for duplicate review
    $dupStmt = $conn->prepare("
        SELECT review_id FROM product_reviews
        WHERE product_id = :pid AND user_id = :uid AND order_id = :oid
        LIMIT 1
    ");
    $dupStmt->bindValue(':pid', $product_id,  PDO::PARAM_INT);
    $dupStmt->bindValue(':uid', $customer_id, PDO::PARAM_INT);
    $dupStmt->bindValue(':oid', $order_id,    PDO::PARAM_INT);
    $dupStmt->execute();

    if ($dupStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'You have already rated this product']);
        exit;
    }

    // Insert review (auto-approved, verified purchase)
    $insStmt = $conn->prepare("
        INSERT INTO product_reviews
            (product_id, user_id, order_id, rating, review_text, is_verified_purchase, is_approved)
        VALUES
            (:pid, :uid, :oid, :rating, :text, 1, 1)
    ");
    $insStmt->bindValue(':pid',    $product_id,  PDO::PARAM_INT);
    $insStmt->bindValue(':uid',    $customer_id, PDO::PARAM_INT);
    $insStmt->bindValue(':oid',    $order_id,    PDO::PARAM_INT);
    $insStmt->bindValue(':rating', $rating,      PDO::PARAM_INT);
    $insStmt->bindValue(':text',   $review_text ?: null,
                        $review_text ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $insStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Thank you for your rating!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

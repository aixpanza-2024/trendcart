<?php
/**
 * Customer - Toggle a product in/out of the wishlist
 * POST: { product_id }
 * Adds it if absent, removes it if present. Requires customer session/token.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$user_id = TokenAuth::requireCustomer($conn, 'Please login to use your wishlist');

$data = json_decode(file_get_contents('php://input'), true);
$product_id = (int)($data['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'product_id is required']);
    exit;
}

try {
    $prod = $conn->prepare("SELECT product_id FROM products WHERE product_id = :pid LIMIT 1");
    $prod->bindValue(':pid', $product_id, PDO::PARAM_INT);
    $prod->execute();
    if (!$prod->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    $exists = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = :uid AND product_id = :pid LIMIT 1");
    $exists->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $exists->bindValue(':pid', $product_id, PDO::PARAM_INT);
    $exists->execute();

    if ($exists->fetch()) {
        $del = $conn->prepare("DELETE FROM wishlist WHERE user_id = :uid AND product_id = :pid");
        $del->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $del->bindValue(':pid', $product_id, PDO::PARAM_INT);
        $del->execute();
        echo json_encode(['success' => true, 'message' => 'Removed from wishlist', 'data' => ['wishlisted' => false]]);
    } else {
        $ins = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (:uid, :pid)");
        $ins->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $ins->bindValue(':pid', $product_id, PDO::PARAM_INT);
        $ins->execute();
        echo json_encode(['success' => true, 'message' => 'Added to wishlist', 'data' => ['wishlisted' => true]]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

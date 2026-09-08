<?php
/**
 * Admin Delete Shop API
 * Deletes a shop, all its products, and associated data.
 * Deactivates (does not delete) the owner user account to preserve any customer order history.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['shop_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Shop ID required']);
    exit();
}

$shop_id = (int)$data['shop_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get shop + user info
    $stmt = $conn->prepare("SELECT s.user_id, s.shop_name, s.shop_logo FROM shops s WHERE s.shop_id = :id");
    $stmt->bindValue(':id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();
    $shop = $stmt->fetch();

    if (!$shop) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Shop not found']);
        exit();
    }

    $user_id = $shop['user_id'];

    // Collect product image paths for physical cleanup
    $stmt = $conn->prepare("SELECT image_url FROM products WHERE shop_id = :id AND image_url IS NOT NULL");
    $stmt->bindValue(':id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();
    $productImages = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Collect product_images table paths
    $stmt = $conn->prepare("
        SELECT pi.image_url FROM product_images pi
        INNER JOIN products p ON pi.product_id = p.product_id
        WHERE p.shop_id = :id
    ");
    $stmt->bindValue(':id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();
    $extraImages = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $allImages = array_filter(array_merge($productImages, $extraImages));

    $conn->beginTransaction();

    // Delete shop_profiles (linked to user_id, not shop_id — no cascade from shop)
    $stmt = $conn->prepare("DELETE FROM shop_profiles WHERE user_id = :uid");
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete shop (cascades → products → product_sizes, order_items, reviews, wishlist, product_images, shop_payments)
    $stmt = $conn->prepare("DELETE FROM shops WHERE shop_id = :id");
    $stmt->bindValue(':id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();

    // Deactivate user account (keep record to preserve customer order history)
    $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE user_id = :uid");
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Log activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, description, ip_address)
        VALUES (:admin_id, 'delete', 'shop', :entity_id, :desc, :ip)
    ");
    $stmt->bindValue(':admin_id',  $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':entity_id', $shop_id, PDO::PARAM_INT);
    $log_desc = 'Deleted shop: ' . $shop['shop_name'];
    $stmt->bindValue(':desc', $log_desc, PDO::PARAM_STR);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();

    $conn->commit();

    // Clean up physical image files after successful DB delete
    $base = dirname(__DIR__, 2); // project root
    foreach ($allImages as $imgPath) {
        $full = $base . '/' . ltrim($imgPath, '/');
        if (file_exists($full)) {
            @unlink($full);
        }
    }
    if ($shop['shop_logo']) {
        $logoFull = $base . '/' . ltrim($shop['shop_logo'], '/');
        if (file_exists($logoFull)) {
            @unlink($logoFull);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Shop and all its products deleted successfully']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

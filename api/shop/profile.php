<?php
/**
 * Shop Profile API
 * GET: returns shop profile
 * POST: updates shop profile
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (($_SESSION['user_type'] ?? '') !== 'shop') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("
            SELECT s.shop_id, s.shop_name, s.shop_description, s.shop_status,
                   s.shop_address, s.shop_city, s.shop_state, s.shop_pincode,
                   s.shop_phone, s.shop_email, s.rating_average, s.total_ratings,
                   s.total_products, s.total_orders, s.total_sales, s.created_at,
                   u.full_name, u.email, u.phone,
                   sp.description as profile_description, sp.shop_image,
                   sp.gst_number, sp.business_registration, sp.shop_slug
            FROM shops s
            INNER JOIN users u ON s.user_id = u.user_id
            LEFT JOIN shop_profiles sp ON s.user_id = sp.user_id
            WHERE s.user_id = :uid
            LIMIT 1
        ");
        $stmt->bindParam(':uid', $user_id);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            echo json_encode(['success' => false, 'message' => 'Shop not found']);
            exit();
        }

        echo json_encode(['success' => true, 'data' => $profile]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }

        // Update shops table
        $stmt = $conn->prepare("
            UPDATE shops SET
                shop_name = :name,
                shop_description = :desc,
                shop_city = :city,
                shop_state = :state,
                shop_pincode = :pincode,
                shop_phone = :phone,
                shop_email = :email,
                shop_address = :address
            WHERE user_id = :uid
        ");
        $stmt->bindValue(':name', $data['shop_name'] ?? '');
        $stmt->bindValue(':desc', $data['shop_description'] ?? '');
        $stmt->bindValue(':city', $data['shop_city'] ?? '');
        $stmt->bindValue(':state', $data['shop_state'] ?? '');
        $stmt->bindValue(':pincode', $data['shop_pincode'] ?? '');
        $stmt->bindValue(':phone', $data['shop_phone'] ?? '');
        $stmt->bindValue(':email', $data['shop_email'] ?? '');
        $stmt->bindValue(':address', $data['shop_address'] ?? '');
        $stmt->bindParam(':uid', $user_id);
        $stmt->execute();

        // Update shop_profiles table
        $stmt = $conn->prepare("
            UPDATE shop_profiles SET
                shop_name = :name,
                description = :desc,
                gst_number = :gst
            WHERE user_id = :uid
        ");
        $stmt->bindValue(':name', $data['shop_name'] ?? '');
        $stmt->bindValue(':desc', $data['shop_description'] ?? '');
        $stmt->bindValue(':gst', $data['gst_number'] ?? '');
        $stmt->bindParam(':uid', $user_id);
        $stmt->execute();

        // Update users phone
        if (!empty($data['owner_phone'])) {
            $stmt = $conn->prepare("UPDATE users SET phone = :phone WHERE user_id = :uid");
            $stmt->bindParam(':phone', $data['owner_phone']);
            $stmt->bindParam(':uid', $user_id);
            $stmt->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

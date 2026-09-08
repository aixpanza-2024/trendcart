<?php
/**
 * Admin Update Shop Details API
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

if (empty($data['shop_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Shop name is required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get user_id for this shop
    $stmt = $conn->prepare("SELECT user_id FROM shops WHERE shop_id = :id");
    $stmt->bindValue(':id', $data['shop_id'], PDO::PARAM_INT);
    $stmt->execute();
    $shop = $stmt->fetch();
    if (!$shop) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Shop not found']);
        exit();
    }
    $user_id = $shop['user_id'];

    $conn->beginTransaction();

    // Update users table (owner name + phone)
    $stmt = $conn->prepare("UPDATE users SET full_name = :name, phone = :phone WHERE user_id = :uid");
    $stmt->bindValue(':name',  $data['owner_name'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':phone', $data['owner_phone'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':uid',   $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Update shops table
    $stmt = $conn->prepare("
        UPDATE shops SET
            shop_name        = :shop_name,
            shop_description = :shop_desc,
            shop_city        = :shop_city,
            shop_pincode     = :shop_pincode,
            shop_phone       = :shop_phone,
            shop_email       = :shop_email,
            shop_status      = :shop_status
        WHERE shop_id = :shop_id
    ");
    $stmt->bindValue(':shop_name',    $data['shop_name'], PDO::PARAM_STR);
    $stmt->bindValue(':shop_desc',    $data['shop_description'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':shop_city',    $data['shop_city'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':shop_pincode', $data['shop_pincode'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':shop_phone',   $data['shop_phone'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':shop_email',   $data['shop_email'] ?? '', PDO::PARAM_STR);
    $valid_statuses = ['open', 'closed', 'suspended'];
    $status = in_array($data['shop_status'] ?? '', $valid_statuses) ? $data['shop_status'] : 'open';
    $stmt->bindValue(':shop_status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':shop_id',     $data['shop_id'], PDO::PARAM_INT);
    $stmt->execute();

    // Also sync shop_profiles name if exists
    $stmt = $conn->prepare("UPDATE shop_profiles SET shop_name = :name, description = :desc WHERE user_id = :uid");
    $stmt->bindValue(':name', $data['shop_name'], PDO::PARAM_STR);
    $stmt->bindValue(':desc', $data['shop_description'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':uid',  $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // If status changed to suspended, deactivate user; if unsuspended, reactivate
    if ($status === 'suspended') {
        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE user_id = :uid");
    } else {
        $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE user_id = :uid");
    }
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Log activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, description, ip_address)
        VALUES (:admin_id, 'update', 'shop', :entity_id, :desc, :ip)
    ");
    $stmt->bindValue(':admin_id',   $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':entity_id',  $data['shop_id'], PDO::PARAM_INT);
    $log_desc = 'Updated shop details: ' . $data['shop_name'];
    $stmt->bindValue(':desc', $log_desc, PDO::PARAM_STR);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Shop updated successfully']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

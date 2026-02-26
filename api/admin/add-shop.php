<?php
/**
 * Admin Add Shop API
 * Creates a new shop owner account and shop entry
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['full_name']) || empty($data['email']) || empty($data['phone']) || empty($data['shop_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $data['email']);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit();
    }

    $conn->beginTransaction();

    // Create user account
    $stmt = $conn->prepare("
        INSERT INTO users (full_name, email, phone, user_type, is_active, is_verified)
        VALUES (:name, :email, :phone, 'shop', 1, 1)
    ");
    $stmt->bindParam(':name', $data['full_name']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':phone', $data['phone']);
    $stmt->execute();
    $user_id = $conn->lastInsertId();

    // Create shop profile
    $shop_slug = strtolower(str_replace(' ', '-', $data['shop_name'])) . '-' . $user_id;
    $stmt = $conn->prepare("
        INSERT INTO shop_profiles (user_id, shop_name, shop_slug, description)
        VALUES (:uid, :name, :slug, :desc)
    ");
    $stmt->bindParam(':uid', $user_id);
    $stmt->bindParam(':name', $data['shop_name']);
    $stmt->bindParam(':slug', $shop_slug);
    $desc = $data['shop_description'] ?? '';
    $stmt->bindParam(':desc', $desc);
    $stmt->execute();

    // Create shops table entry
    $stmt = $conn->prepare("
        INSERT INTO shops (user_id, shop_name, shop_description, shop_city, shop_phone, shop_email, shop_status)
        VALUES (:uid, :name, :desc, :city, :phone, :email, 'open')
    ");
    $stmt->bindParam(':uid', $user_id);
    $stmt->bindParam(':name', $data['shop_name']);
    $stmt->bindParam(':desc', $desc);
    $city = $data['shop_city'] ?? '';
    $stmt->bindParam(':city', $city);
    $shop_phone = $data['shop_phone'] ?? $data['phone'];
    $stmt->bindParam(':phone', $shop_phone);
    $stmt->bindParam(':email', $data['email']);
    $stmt->execute();

    // Log admin activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, description, ip_address)
        VALUES (:admin_id, 'create', 'shop', :entity_id, :desc, :ip)
    ");
    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $stmt->bindParam(':entity_id', $user_id);
    $log_desc = 'Created shop: ' . $data['shop_name'];
    $stmt->bindParam(':desc', $log_desc);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt->bindParam(':ip', $ip);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Shop created successfully', 'data' => ['user_id' => $user_id]]);

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

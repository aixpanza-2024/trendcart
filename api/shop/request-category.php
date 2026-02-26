<?php
/**
 * Shop Request New Category API
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

$data = json_decode(file_get_contents('php://input'), true);

// Ensure table exists (handles first-run without manual SQL import)
try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->exec("
        CREATE TABLE IF NOT EXISTS category_requests (
            request_id    INT PRIMARY KEY AUTO_INCREMENT,
            shop_id       INT NOT NULL,
            user_id       INT NOT NULL,
            category_name VARCHAR(255) NOT NULL,
            parent_name   VARCHAR(255) NULL,
            note          TEXT NULL,
            status        ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at   TIMESTAMP NULL,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) { /* ignore — table already exists */ }

if (!$data || empty($data['category_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get shop_id
    $stmt = $conn->prepare("SELECT shop_id FROM shops WHERE user_id = :uid LIMIT 1");
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        echo json_encode(['success' => false, 'message' => 'Shop not found']);
        exit();
    }

    $shop_id    = $shop['shop_id'];
    $cat_name   = trim($data['category_name']);
    $parent     = trim($data['parent_name'] ?? '');
    $note       = trim($data['note'] ?? '');

    $stmt = $conn->prepare("
        INSERT INTO category_requests (shop_id, user_id, category_name, parent_name, note)
        VALUES (:shop_id, :user_id, :cat, :parent, :note)
    ");
    $stmt->bindParam(':shop_id', $shop_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':cat',     $cat_name);
    $stmt->bindParam(':parent',  $parent);
    $stmt->bindParam(':note',    $note);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Request submitted. Admin will review shortly.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

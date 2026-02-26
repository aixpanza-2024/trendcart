<?php
/**
 * Admin - List Category Requests
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Ensure table exists
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

    $status = $_GET['status'] ?? 'pending';

    $stmt = $conn->prepare("
        SELECT cr.*, s.shop_name, u.full_name as owner_name, u.email as owner_email
        FROM category_requests cr
        INNER JOIN shops s ON cr.shop_id = s.shop_id
        INNER JOIN users u ON cr.user_id = u.user_id
        WHERE cr.status = :status
        ORDER BY cr.created_at DESC
    ");
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also return count of pending for badge
    $stmt2 = $conn->query("SELECT COUNT(*) FROM category_requests WHERE status = 'pending'");
    $pending_count = (int) $stmt2->fetchColumn();

    echo json_encode([
        'success' => true,
        'data'    => $requests,
        'pending_count' => $pending_count
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

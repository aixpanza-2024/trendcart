<?php
/**
 * Admin Shop OTP List API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $stmt = $conn->prepare("
        SELECT
            ov.otp_id,
            ov.email,
            ov.otp_code,
            ov.purpose,
            ov.is_used,
            ov.expires_at,
            ov.created_at,
            COALESCE(s.shop_name, sp.shop_name, u.full_name) AS shop_name
        FROM otp_verification ov
        INNER JOIN users u ON (
            (ov.user_id IS NOT NULL AND ov.user_id = u.user_id)
            OR (ov.user_id IS NULL AND ov.email = u.email)
        )
        LEFT JOIN shops s ON u.user_id = s.user_id
        LEFT JOIN shop_profiles sp ON u.user_id = sp.user_id
        WHERE u.user_type = 'shop'
        ORDER BY ov.created_at DESC, ov.otp_id DESC
        LIMIT 5
    ");

    $stmt->execute();
    $otps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $otps]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

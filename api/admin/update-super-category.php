<?php
/**
 * Admin Update Super Category API
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

if (!$data || empty($data['super_category_id']) || empty($data['super_category_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Super category ID and name required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $sets   = "super_category_name=:name, display_order=:display_order";
    $params = [
        ':name'          => $data['super_category_name'],
        ':display_order' => $data['display_order'] ?? 0,
        ':id'            => $data['super_category_id'],
    ];

    // Only update image if a new one was provided; null means "keep existing"
    if (array_key_exists('super_category_image', $data)) {
        $sets .= ', super_category_image=:image';
        $params[':image'] = !empty($data['super_category_image']) ? $data['super_category_image'] : null;
    }

    $stmt = $conn->prepare("UPDATE super_categories SET $sets WHERE super_category_id = :id");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Super category updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * Admin Update Category API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['category_id']) || empty($data['category_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category ID and name required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $sets   = "category_name=:name, parent_category_id=:parent, category_description=:desc, display_order=:display_order";
    $params = [
        ':name'          => $data['category_name'],
        ':parent'        => !empty($data['parent_category_id']) ? $data['parent_category_id'] : null,
        ':desc'          => $data['category_description'] ?? '',
        ':display_order' => $data['display_order'] ?? 0,
        ':id'            => $data['category_id'],
    ];

    // Only update image if a new one was provided; null means "keep existing"
    if (array_key_exists('category_image', $data)) {
        $sets .= ', category_image=:image';
        $params[':image'] = !empty($data['category_image']) ? $data['category_image'] : null;
    }

    $stmt = $conn->prepare("UPDATE categories SET $sets WHERE category_id = :id");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Category updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

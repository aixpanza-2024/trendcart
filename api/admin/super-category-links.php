<?php
/**
 * Admin Super Category Links API
 * GET  ?super_category_id=X  -> list of linked category_ids
 * POST { super_category_id, category_ids: [...] } -> replaces the full link set
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

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $super_category_id = isset($_GET['super_category_id']) ? (int)$_GET['super_category_id'] : 0;
        if (!$super_category_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'super_category_id is required']);
            exit();
        }

        $stmt = $conn->prepare("SELECT category_id FROM super_category_links WHERE super_category_id = :scid");
        $stmt->bindValue(':scid', $super_category_id, PDO::PARAM_INT);
        $stmt->execute();
        $categoryIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        echo json_encode(['success' => true, 'data' => $categoryIds]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || empty($data['super_category_id']) || !isset($data['category_ids']) || !is_array($data['category_ids'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'super_category_id and category_ids[] are required']);
            exit();
        }

        $super_category_id = (int)$data['super_category_id'];
        $categoryIds = array_values(array_unique(array_map('intval', $data['category_ids'])));

        $conn->beginTransaction();

        $del = $conn->prepare("DELETE FROM super_category_links WHERE super_category_id = :scid");
        $del->bindValue(':scid', $super_category_id, PDO::PARAM_INT);
        $del->execute();

        if (!empty($categoryIds)) {
            $ins = $conn->prepare("INSERT INTO super_category_links (super_category_id, category_id) VALUES (:scid, :cid)");
            foreach ($categoryIds as $cid) {
                $ins->bindValue(':scid', $super_category_id, PDO::PARAM_INT);
                $ins->bindValue(':cid', $cid, PDO::PARAM_INT);
                $ins->execute();
            }
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Links updated']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

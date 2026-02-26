<?php
/**
 * Admin - Approve or Reject a Category Request
 * On approve: auto-creates the category
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['request_id']) || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'request_id and action required']);
    exit();
}

if (!in_array($data['action'], ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch the request
    $stmt = $conn->prepare("SELECT * FROM category_requests WHERE request_id = :id AND status = 'pending'");
    $stmt->bindParam(':id', $data['request_id']);
    $stmt->execute();
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found or already reviewed']);
        exit();
    }

    $conn->beginTransaction();

    if ($data['action'] === 'approve') {
        // Resolve parent category id if parent_name was given
        $parent_id = null;
        if (!empty($req['parent_name'])) {
            $ps = $conn->prepare("SELECT category_id FROM categories WHERE category_name = :name LIMIT 1");
            $ps->bindParam(':name', $req['parent_name']);
            $ps->execute();
            $row = $ps->fetch(PDO::FETCH_ASSOC);
            $parent_id = $row ? $row['category_id'] : null;
        }

        // Create the category
        $stmt = $conn->prepare("
            INSERT INTO categories (category_name, parent_category_id, is_active)
            VALUES (:name, :parent, 1)
        ");
        $stmt->bindParam(':name',   $req['category_name']);
        $stmt->bindParam(':parent', $parent_id);
        $stmt->execute();
    }

    // Mark request as approved / rejected
    $new_status = $data['action'] === 'approve' ? 'approved' : 'rejected';
    $stmt = $conn->prepare("
        UPDATE category_requests
        SET status = :status, reviewed_at = NOW()
        WHERE request_id = :id
    ");
    $stmt->bindParam(':status', $new_status);
    $stmt->bindParam(':id', $data['request_id']);
    $stmt->execute();

    $conn->commit();

    $msg = $data['action'] === 'approve'
        ? 'Category approved and created successfully'
        : 'Request rejected';

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * Customer - Set an address as the default
 * POST: { address_id }
 * Requires customer session/token
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$user_id = TokenAuth::requireCustomer($conn, 'Please login to manage addresses');

$data = json_decode(file_get_contents('php://input'), true);
$address_id = (int)($data['address_id'] ?? 0);

if ($address_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'address_id is required']);
    exit;
}

try {
    $own = $conn->prepare("SELECT address_id FROM addresses WHERE address_id = :id AND user_id = :uid LIMIT 1");
    $own->bindValue(':id', $address_id, PDO::PARAM_INT);
    $own->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $own->execute();
    if (!$own->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Address not found']);
        exit;
    }

    $conn->beginTransaction();

    $clear = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = :uid");
    $clear->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $clear->execute();

    $set = $conn->prepare("UPDATE addresses SET is_default = 1 WHERE address_id = :id AND user_id = :uid");
    $set->bindValue(':id', $address_id, PDO::PARAM_INT);
    $set->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $set->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Default address updated']);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

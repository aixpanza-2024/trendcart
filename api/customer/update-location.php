<?php
/**
 * Customer - Update Current Location
 * POST: { latitude: 8.5241, longitude: 76.9366 }
 * Called by the mobile app whenever it gets a fresh GPS fix.
 * Requires customer login (session or Bearer token).
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

$user_id = TokenAuth::requireCustomer($conn, 'Please login to update location');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['latitude']) || !isset($data['longitude'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'latitude and longitude are required']);
    exit;
}

$latitude  = (float)$data['latitude'];
$longitude = (float)$data['longitude'];

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid latitude/longitude']);
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO customer_profiles (user_id, latitude, longitude, location_updated_at)
        VALUES (:uid, :lat, :lng, NOW())
        ON DUPLICATE KEY UPDATE
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            location_updated_at = VALUES(location_updated_at)
    ");
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':lat', $latitude);
    $stmt->bindValue(':lng', $longitude);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Location updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

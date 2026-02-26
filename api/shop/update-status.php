<?php
/**
 * Update Shop Status API
 * Toggle shop status between open and closed
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Include required files
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login.'
    ]);
    exit();
}

// Check if user is shop owner
if ($_SESSION['user_type'] !== 'shop') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Shop owners only.'
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Status is required.'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$status = $data['status'];

// Validate status
if (!in_array($status, ['open', 'closed'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status. Must be "open" or "closed".'
    ]);
    exit();
}

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Update shop status
    $query = "UPDATE shops SET shop_status = :status WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Shop status updated successfully',
            'data' => [
                'status' => $status
            ]
        ]);
    } else {
        throw new Exception('Failed to update shop status');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

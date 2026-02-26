<?php
/**
 * Customer - Profile API (GET / POST)
 * Requires customer session
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    /* ───── GET ───── */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare(
            "SELECT u.user_id, u.full_name, u.email, u.phone,
                    cp.date_of_birth, cp.gender, cp.profile_image
             FROM users u
             LEFT JOIN customer_profiles cp ON u.user_id = cp.user_id
             WHERE u.user_id = :user_id LIMIT 1"
        );
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            echo json_encode(['success' => false, 'message' => 'Profile not found']);
            exit;
        }

        // Fetch default address
        $addrStmt = $conn->prepare(
            "SELECT * FROM addresses WHERE user_id = :uid AND is_default = 1 LIMIT 1"
        );
        $addrStmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $addrStmt->execute();
        $profile['default_address'] = $addrStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        echo json_encode(['success' => true, 'data' => $profile]);
        exit;
    }

    /* ───── POST (update) ───── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'));

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid request body']);
            exit;
        }

        // Update users table
        if (!empty($data->full_name) || !empty($data->phone)) {
            $sets   = [];
            $params = [':user_id' => $user_id];
            if (!empty($data->full_name)) { $sets[] = 'full_name = :full_name'; $params[':full_name'] = trim($data->full_name); }
            if (!empty($data->phone))     { $sets[] = 'phone = :phone';         $params[':phone']     = trim($data->phone); }
            if ($sets) {
                $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE user_id = :user_id");
                foreach ($params as $k => $v) $stmt->bindValue($k, $v);
                $stmt->execute();
            }
        }

        // Upsert customer_profiles
        $conn->prepare(
            "INSERT INTO customer_profiles (user_id, date_of_birth, gender)
             VALUES (:uid, :dob, :gender)
             ON DUPLICATE KEY UPDATE date_of_birth = VALUES(date_of_birth), gender = VALUES(gender)"
        )->execute([
            ':uid'    => $user_id,
            ':dob'    => !empty($data->date_of_birth) ? $data->date_of_birth : null,
            ':gender' => !empty($data->gender) ? $data->gender : null
        ]);

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

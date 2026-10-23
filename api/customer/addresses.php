<?php
/**
 * Customer - Address Book
 * GET  : list the logged-in customer's saved addresses (default first)
 * POST : add a new address
 * Requires customer session/token
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/AddressInput.php';
require_once __DIR__ . '/../utils/InputClean.php';

$database = new Database();
$conn = $database->getConnection();

$user_id = TokenAuth::requireCustomer($conn, 'Please login to manage addresses');

const MAX_ADDRESSES_PER_USER = 10;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare(
            "SELECT address_id, address_type, full_name, phone, address_line1, address_line2,
                    city, state, pincode, latitude, longitude, is_default, created_at
             FROM addresses
             WHERE user_id = :uid
             ORDER BY is_default DESC, created_at DESC"
        );
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid request body']);
            exit;
        }

        $full_name     = InputClean::line($data['full_name'] ?? '', 100);
        $phone         = AddressInput::mobile($data['phone'] ?? '');
        $address_line1 = InputClean::line($data['address_line1'] ?? '', 255);
        $address_line2 = InputClean::line($data['address_line2'] ?? '', 255);
        $city          = InputClean::line($data['city'] ?? '', 100);
        $state         = InputClean::line($data['state'] ?? '', 100);
        $pincode       = trim($data['pincode'] ?? '');
        $address_type_in = $data['address_type'] ?? 'home';
        $address_type    = in_array($address_type_in, ['home', 'work', 'other'], true) ? $address_type_in : 'home';
        $make_default  = !empty($data['is_default']);
        // Optional map pin — delivery fee is distance-based when present
        [$latitude, $longitude] = AddressInput::coords($data);

        if ($full_name === '' || $address_line1 === '' || $city === '' || $state === '') {
            echo json_encode(['success' => false, 'message' => 'Name, address line 1, city and state are required']);
            exit;
        }
        if ($phone === null) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid 10-digit mobile number']);
            exit;
        }
        if (!preg_match('/^\d{6}$/', $pincode)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid 6-digit pincode']);
            exit;
        }

        $countStmt = $conn->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = :uid");
        $countStmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $countStmt->execute();
        $existing = (int)$countStmt->fetchColumn();

        if ($existing >= MAX_ADDRESSES_PER_USER) {
            echo json_encode(['success' => false, 'message' => 'You can save up to ' . MAX_ADDRESSES_PER_USER . ' addresses. Delete one to add another.']);
            exit;
        }

        // First address is always the default, regardless of what was sent
        $is_default = $make_default || $existing === 0;

        $conn->beginTransaction();

        if ($is_default) {
            $clear = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = :uid");
            $clear->bindValue(':uid', $user_id, PDO::PARAM_INT);
            $clear->execute();
        }

        $ins = $conn->prepare(
            "INSERT INTO addresses
                (user_id, address_type, full_name, phone, address_line1, address_line2, city, state, pincode, latitude, longitude, is_default)
             VALUES
                (:uid, :type, :name, :phone, :line1, :line2, :city, :state, :pincode, :lat, :lng, :is_default)"
        );
        $ins->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $ins->bindValue(':type', $address_type);
        $ins->bindValue(':name', $full_name);
        $ins->bindValue(':phone', $phone);
        $ins->bindValue(':line1', $address_line1);
        $ins->bindValue(':line2', $address_line2 !== '' ? $address_line2 : null);
        $ins->bindValue(':city', $city);
        $ins->bindValue(':state', $state);
        $ins->bindValue(':pincode', $pincode);
        $ins->bindValue(':lat', $latitude);
        $ins->bindValue(':lng', $longitude);
        $ins->bindValue(':is_default', $is_default ? 1 : 0, PDO::PARAM_INT);
        $ins->execute();

        $address_id = (int)$conn->lastInsertId();
        $conn->commit();

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Address saved', 'data' => ['address_id' => $address_id]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

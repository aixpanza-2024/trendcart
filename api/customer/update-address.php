<?php
/**
 * Customer - Update a saved address
 * POST: { address_id, full_name, phone, address_line1, address_line2, city, state, pincode, address_type, is_default,
 *         latitude?, longitude? }  — omit both to leave the saved map pin unchanged, send null to clear it
 * Requires customer session/token
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/AddressInput.php';
require_once __DIR__ . '/../utils/InputClean.php';

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
// Older app builds don't send a pin at all — only touch it when the keys are present
$set_coords    = array_key_exists('latitude', $data ?? []) || array_key_exists('longitude', $data ?? []);
[$latitude, $longitude] = AddressInput::coords($data ?? []);

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

try {
    // Ownership check — never let a customer edit someone else's address
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

    if ($make_default) {
        $clear = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = :uid");
        $clear->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $clear->execute();
    }

    $upd = $conn->prepare(
        "UPDATE addresses SET
            address_type = :type, full_name = :name, phone = :phone,
            address_line1 = :line1, address_line2 = :line2,
            city = :city, state = :state, pincode = :pincode
            " . ($set_coords ? ', latitude = :lat, longitude = :lng' : '') . "
            " . ($make_default ? ', is_default = 1' : '') . "
         WHERE address_id = :id AND user_id = :uid"
    );
    $upd->bindValue(':type', $address_type);
    $upd->bindValue(':name', $full_name);
    $upd->bindValue(':phone', $phone);
    $upd->bindValue(':line1', $address_line1);
    $upd->bindValue(':line2', $address_line2 !== '' ? $address_line2 : null);
    $upd->bindValue(':city', $city);
    $upd->bindValue(':state', $state);
    $upd->bindValue(':pincode', $pincode);
    if ($set_coords) {
        $upd->bindValue(':lat', $latitude);
        $upd->bindValue(':lng', $longitude);
    }
    $upd->bindValue(':id', $address_id, PDO::PARAM_INT);
    $upd->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $upd->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Address updated']);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

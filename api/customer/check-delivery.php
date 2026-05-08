<?php
/**
 * Customer - Check Delivery Zone by Pincode
 * GET/POST: { pincode: "695582" }
 */
header('Content-Type: application/json');
require_once '../config/database.php';

$pincode = trim($_GET['pincode'] ?? (json_decode(file_get_contents('php://input'), true)['pincode'] ?? ''));

if (!preg_match('/^\d{6}$/', $pincode)) {
    echo json_encode(['success' => false, 'message' => 'Invalid pincode']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Check if pincode is in a zone
    $stmt = $conn->prepare("
        SELECT z.zone_id, z.zone_name, z.delivery_fee, dp.area_name
        FROM delivery_pincodes dp
        INNER JOIN delivery_zones z ON dp.zone_id = z.zone_id
        WHERE dp.pincode = :pin AND z.is_active = 1
        LIMIT 1
    ");
    $stmt->bindValue(':pin', $pincode);
    $stmt->execute();
    $zone = $stmt->fetch();

    if ($zone) {
        echo json_encode([
            'success'      => true,
            'zone_id'      => $zone['zone_id'],
            'zone_name'    => $zone['zone_name'],
            'delivery_fee' => (float)$zone['delivery_fee'],
            'area_name'    => $zone['area_name'],
            'is_free'      => (float)$zone['delivery_fee'] === 0.0,
        ]);
    } else {
        // Pincode not in Zone 1 — apply default zone
        $defStmt = $conn->query("SELECT zone_id, zone_name, delivery_fee FROM delivery_zones WHERE is_default_zone = 1 AND is_active = 1 LIMIT 1");
        $default = $defStmt->fetch();

        echo json_encode([
            'success'      => true,
            'zone_id'      => $default ? $default['zone_id'] : 2,
            'zone_name'    => $default ? $default['zone_name'] : 'Zone 2 - Standard Delivery',
            'delivery_fee' => $default ? (float)$default['delivery_fee'] : 49.00,
            'area_name'    => null,
            'is_free'      => false,
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * Customer - Check Delivery Zone by Pincode
 * GET: pincode=695582&shop_id=3
 * Same zone as shop = free. Different zone = standard fee. No shop pincode = free.
 */
header('Content-Type: application/json');
require_once '../config/database.php';

$pincode = trim($_GET['pincode'] ?? '');
$shop_id = (int)($_GET['shop_id'] ?? 0);

if (!preg_match('/^\d{6}$/', $pincode)) {
    echo json_encode(['success' => false, 'message' => 'Invalid pincode']);
    exit;
}

try {
    $database = new Database();
    $conn     = $database->getConnection();

    // Look up customer's zone
    $stmt = $conn->prepare("
        SELECT z.zone_id, z.zone_name, z.delivery_fee, dp.area_name
        FROM delivery_pincodes dp
        INNER JOIN delivery_zones z ON dp.zone_id = z.zone_id
        WHERE dp.pincode = :pin AND z.is_active = 1
        LIMIT 1
    ");
    $stmt->bindValue(':pin', $pincode);
    $stmt->execute();
    $customerZone = $stmt->fetch();

    // Fall back to default zone if customer pincode not found
    $pincodeFound = (bool)$customerZone;
    if (!$customerZone) {
        $defStmt  = $conn->query("SELECT zone_id, zone_name, delivery_fee FROM delivery_zones WHERE is_default_zone = 1 AND is_active = 1 LIMIT 1");
        $defZone  = $defStmt->fetch() ?: ['zone_id' => 0, 'zone_name' => 'Standard Zone', 'delivery_fee' => 49.00, 'area_name' => null];
        // Use zone_id = -1 so it never matches the shop's zone — ensures default fee is charged
        $customerZone = array_merge($defZone, ['zone_id' => -1, 'area_name' => null]);
    }

    // Look up shop's zone from shop_pincode (if shop_id provided)
    $shopZoneId = null;
    if ($shop_id > 0) {
        $shopStmt = $conn->prepare("
            SELECT z.zone_id
            FROM shops s
            INNER JOIN delivery_pincodes dp ON dp.pincode = s.shop_pincode
            INNER JOIN delivery_zones z    ON dp.zone_id  = z.zone_id
            WHERE s.shop_id = :sid AND z.is_active = 1
            LIMIT 1
        ");
        $shopStmt->bindValue(':sid', $shop_id, PDO::PARAM_INT);
        $shopStmt->execute();
        $shopZone   = $shopStmt->fetch();
        $shopZoneId = $shopZone ? (int)$shopZone['zone_id'] : null;
    }

    // Free only when shop pincode is known AND customer is in the same zone
    $sameZone    = ($shopZoneId !== null && $shopZoneId === (int)$customerZone['zone_id']);
    $deliveryFee = $sameZone ? 0.0 : (float)$customerZone['delivery_fee'];

    echo json_encode([
        'success'      => true,
        'zone_id'      => $customerZone['zone_id'],
        'zone_name'    => $customerZone['zone_name'],
        'delivery_fee' => $deliveryFee,
        'area_name'    => $customerZone['area_name'] ?? null,
        'is_free'      => $deliveryFee === 0.0,
        'same_zone'    => $sameZone,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

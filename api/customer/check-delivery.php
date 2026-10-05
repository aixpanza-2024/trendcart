<?php
/**
 * Customer - Check Delivery Fee for a single shop
 * GET: pincode=695582&shop_id=3&latitude=8.5241&longitude=76.9366
 *
 * If latitude/longitude are given AND the shop has a map location set,
 * the fee is distance-based (see DeliveryCalculator) — pincode is not
 * needed for this path, e.g. a customer checking a shop's delivery fee
 * from their current location before they've typed an address anywhere.
 * Otherwise falls back to the pincode/zone system, which does need one.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/DeliveryCalculator.php';

$pincode   = trim($_GET['pincode'] ?? '');
$shop_id   = (int)($_GET['shop_id'] ?? 0);
$latitude  = isset($_GET['latitude'])  && $_GET['latitude']  !== '' ? (float)$_GET['latitude']  : null;
$longitude = isset($_GET['longitude']) && $_GET['longitude'] !== '' ? (float)$_GET['longitude'] : null;

$has_coords = ($latitude !== null && $longitude !== null);

if (!$has_coords && !preg_match('/^\d{6}$/', $pincode)) {
    echo json_encode(['success' => false, 'message' => 'Invalid pincode']);
    exit;
}

try {
    $database = new Database();
    $conn     = $database->getConnection();

    $shopLat = $shopLng = null;
    if ($shop_id > 0) {
        $shopStmt = $conn->prepare("SELECT latitude, longitude FROM shops WHERE shop_id = :sid LIMIT 1");
        $shopStmt->bindValue(':sid', $shop_id, PDO::PARAM_INT);
        $shopStmt->execute();
        $shopRow = $shopStmt->fetch(PDO::FETCH_ASSOC);
        if ($shopRow) {
            $shopLat = $shopRow['latitude']  !== null ? (float)$shopRow['latitude']  : null;
            $shopLng = $shopRow['longitude'] !== null ? (float)$shopRow['longitude'] : null;
        }
    }

    $result = DeliveryCalculator::distanceFee($conn, $shopLat, $shopLng, $latitude, $longitude);

    if ($result) {
        echo json_encode([
            'success'      => true,
            'method'       => 'distance',
            'distance_km'  => $result['distance_km'],
            'delivery_fee' => $result['fee'],
            'is_free'      => $result['fee'] === 0.0,
        ]);
        exit;
    }

    // Fallback: pincode/zone system — only possible with an actual pincode.
    // (Coordinates were given but the shop has no map location set yet, and
    // no pincode was sent either — e.g. a "use my location" check on the
    // shop page, before any address has been typed. Nothing to quote yet.)
    if (!preg_match('/^\d{6}$/', $pincode)) {
        echo json_encode(['success' => false, 'message' => 'This shop has not set a delivery location yet — enter your pincode to check delivery.']);
        exit;
    }

    $zone = DeliveryCalculator::zoneFee($conn, $shop_id, $pincode);

    echo json_encode([
        'success'      => true,
        'method'       => 'zone',
        'zone_name'    => $zone['zone_name'],
        'area_name'    => $zone['area_name'],
        'delivery_fee' => $zone['fee'],
        'is_free'      => $zone['fee'] === 0.0,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

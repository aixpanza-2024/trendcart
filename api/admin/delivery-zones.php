<?php
/**
 * Admin Delivery Zones API
 * GET  ?type=zones|pincodes            — list
 * POST action=save_zone|save_pincode   — create/update
 * DELETE action=delete_zone|delete_pincode — remove
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$conn     = $database->getConnection();
$method   = $_SERVER['REQUEST_METHOD'];

try {

    if ($method === 'GET') {
        $type = $_GET['type'] ?? 'zones';

        if ($type === 'pincodes') {
            $zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
            $sql = "SELECT dp.pincode_id, dp.pincode, dp.area_name, dp.zone_id, z.zone_name
                    FROM delivery_pincodes dp
                    INNER JOIN delivery_zones z ON dp.zone_id = z.zone_id";
            if ($zone_id > 0) $sql .= " WHERE dp.zone_id = $zone_id";
            $sql .= " ORDER BY dp.zone_id ASC, dp.pincode ASC";
            $stmt = $conn->query($sql);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } else {
            $stmt = $conn->query("SELECT * FROM delivery_zones ORDER BY zone_id ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        }
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    /* ── Save Zone (create or update) ── */
    if ($action === 'save_zone') {
        $zone_id      = (int)($input['zone_id'] ?? 0);
        $zone_name    = trim($input['zone_name'] ?? '');
        $delivery_fee = (float)($input['delivery_fee'] ?? 0);
        $is_default   = (int)($input['is_default_zone'] ?? 0);
        $is_active    = (int)($input['is_active'] ?? 1);

        if (!$zone_name) { echo json_encode(['success' => false, 'message' => 'Zone name required']); exit; }

        if ($is_default) {
            $conn->prepare("UPDATE delivery_zones SET is_default_zone = 0")->execute();
        }

        if ($zone_id > 0) {
            $stmt = $conn->prepare("UPDATE delivery_zones SET zone_name=:n, delivery_fee=:f, is_default_zone=:d, is_active=:a WHERE zone_id=:id");
            $stmt->bindValue(':id', $zone_id, PDO::PARAM_INT);
        } else {
            $stmt = $conn->prepare("INSERT INTO delivery_zones (zone_name, delivery_fee, is_default_zone, is_active) VALUES (:n,:f,:d,:a)");
        }
        $stmt->bindValue(':n', $zone_name);
        $stmt->bindValue(':f', $delivery_fee);
        $stmt->bindValue(':d', $is_default,  PDO::PARAM_INT);
        $stmt->bindValue(':a', $is_active,   PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Zone saved']);
        exit;
    }

    /* ── Save Pincode ── */
    if ($action === 'save_pincode') {
        $pincode_id = (int)($input['pincode_id'] ?? 0);
        $pincode    = trim($input['pincode'] ?? '');
        $zone_id    = (int)($input['zone_id'] ?? 0);
        $area_name  = trim($input['area_name'] ?? '');

        if (!preg_match('/^\d{6}$/', $pincode)) { echo json_encode(['success' => false, 'message' => 'Invalid pincode (must be 6 digits)']); exit; }
        if ($zone_id <= 0) { echo json_encode(['success' => false, 'message' => 'Zone required']); exit; }

        if ($pincode_id > 0) {
            $stmt = $conn->prepare("UPDATE delivery_pincodes SET pincode=:p, zone_id=:z, area_name=:a WHERE pincode_id=:id");
            $stmt->bindValue(':id', $pincode_id, PDO::PARAM_INT);
        } else {
            $stmt = $conn->prepare("INSERT INTO delivery_pincodes (pincode, zone_id, area_name) VALUES (:p,:z,:a)");
        }
        $stmt->bindValue(':p', $pincode);
        $stmt->bindValue(':z', $zone_id, PDO::PARAM_INT);
        $stmt->bindValue(':a', $area_name ?: null);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Pincode saved']);
        exit;
    }

    /* ── Bulk add pincodes ── */
    if ($action === 'bulk_pincodes') {
        $zone_id  = (int)($input['zone_id'] ?? 0);
        $raw      = trim($input['pincodes'] ?? '');
        if ($zone_id <= 0 || !$raw) { echo json_encode(['success' => false, 'message' => 'Zone and pincodes required']); exit; }

        $lines  = preg_split('/[\s,;]+/', $raw);
        $added  = 0; $skipped = 0;
        $stmt = $conn->prepare("INSERT IGNORE INTO delivery_pincodes (pincode, zone_id, area_name) VALUES (:p,:z,:a)");
        foreach ($lines as $line) {
            $parts   = explode(':', trim($line), 2);
            $pin     = trim($parts[0]);
            $area    = isset($parts[1]) ? trim($parts[1]) : null;
            if (!preg_match('/^\d{6}$/', $pin)) { $skipped++; continue; }
            $stmt->bindValue(':p', $pin);
            $stmt->bindValue(':z', $zone_id, PDO::PARAM_INT);
            $stmt->bindValue(':a', $area);
            $stmt->execute();
            $added += $stmt->rowCount();
        }
        echo json_encode(['success' => true, 'message' => "$added pincodes added, $skipped skipped"]);
        exit;
    }

    /* ── Delete Zone ── */
    if ($action === 'delete_zone') {
        $zone_id = (int)($input['zone_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM delivery_zones WHERE zone_id = :id");
        $stmt->bindValue(':id', $zone_id, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Zone deleted']);
        exit;
    }

    /* ── Delete Pincode ── */
    if ($action === 'delete_pincode') {
        $pincode_id = (int)($input['pincode_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM delivery_pincodes WHERE pincode_id = :id");
        $stmt->bindValue(':id', $pincode_id, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Pincode removed']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

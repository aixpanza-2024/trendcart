<?php
/**
 * Shop Update Order Item Status API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/ProductVariants.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (($_SESSION['user_type'] ?? '') !== 'shop') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_item_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order item ID and status required']);
    exit();
}

$valid = ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($data['status'], $valid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get shop_id and verify ownership
    $stmt = $conn->prepare("SELECT shop_id FROM shops WHERE user_id = :uid LIMIT 1");
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        echo json_encode(['success' => false, 'message' => 'Shop not found']);
        exit();
    }

    $shop_id = $shop['shop_id'];

    $conn->beginTransaction();

    // Verify item belongs to this shop (locked: two clicks can't both restock)
    $stmt = $conn->prepare(
        "SELECT order_item_id, item_status, product_id, variant_id, selected_size, selected_color, quantity
         FROM order_items WHERE order_item_id = :id AND shop_id = :shop_id FOR UPDATE"
    );
    $stmt->bindParam(':id', $data['order_item_id']);
    $stmt->bindParam(':shop_id', $shop_id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $conn->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Order item not found']);
        exit();
    }

    // Items only move forward; delivered and cancelled are final
    $progress = ['pending' => 0, 'confirmed' => 1, 'processing' => 2, 'shipped' => 3, 'delivered' => 4];
    $current  = $item['item_status'];
    $next     = $data['status'];
    if ($current === $next) {
        $conn->rollBack();
        echo json_encode(['success' => true, 'message' => 'Status unchanged']);
        exit();
    }
    if ($current === 'delivered' || $current === 'cancelled') {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "This item is already {$current} and can't be changed."]);
        exit();
    }
    if ($next !== 'cancelled' && ($progress[$next] ?? 0) < ($progress[$current] ?? 0)) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "An item that is {$current} can't go back to {$next}."]);
        exit();
    }

    // Update status
    $stmt = $conn->prepare("UPDATE order_items SET item_status = :status WHERE order_item_id = :id");
    $stmt->bindParam(':status', $next);
    $stmt->bindParam(':id', $data['order_item_id']);
    $stmt->execute();

    // Cancelled by the shop: the stock goes back on the shelf
    if ($next === 'cancelled') {
        ProductVariants::restockOrderItem($conn, $item);
    }

    // Sync parent orders.order_status based on all item statuses for this order
    $syncStmt = $conn->prepare(
        "SELECT order_id, GROUP_CONCAT(item_status) AS all_statuses
         FROM order_items
         WHERE order_id = (SELECT order_id FROM order_items WHERE order_item_id = :id LIMIT 1)
         GROUP BY order_id
         LIMIT 1"
    );
    $syncStmt->bindValue(':id', $data['order_item_id'], PDO::PARAM_INT);
    $syncStmt->execute();
    $syncRow = $syncStmt->fetch(PDO::FETCH_ASSOC);

    if ($syncRow) {
        $priority    = ['pending' => 0, 'confirmed' => 1, 'processing' => 2, 'shipped' => 3, 'delivered' => 4];
        $statuses    = explode(',', $syncRow['all_statuses']);
        $active      = array_filter($statuses, fn($s) => $s !== 'cancelled');

        if (empty($active)) {
            $newOrderStatus = 'cancelled';
        } else {
            // Overall status = minimum progress across all active items
            $minPri         = min(array_map(fn($s) => $priority[$s] ?? 0, $active));
            $newOrderStatus = array_search($minPri, $priority);
        }

        $deliveredStamp = $newOrderStatus === 'delivered' ? ', delivered_at = NOW()' : '';
        $updO = $conn->prepare("UPDATE orders SET order_status = :status{$deliveredStamp} WHERE order_id = :oid");
        $updO->bindValue(':status', $newOrderStatus);
        $updO->bindValue(':oid', (int)$syncRow['order_id'], PDO::PARAM_INT);
        $updO->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Status updated']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

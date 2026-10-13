<?php
/**
 * Customer - Cancel an order
 * POST: { order_id }
 *
 * Only allowed while the shop hasn't started working on it (order_status
 * pending or confirmed). Restores stock for every item. Online payments are
 * left as payment_status = 'paid' — refunds are issued by support, this
 * endpoint only stops fulfillment.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

const CANCELLABLE_STATUSES = ['pending', 'confirmed'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login');

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$order_id = (int)($input['order_id'] ?? 0);

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    $conn->beginTransaction();

    $orderStmt = $conn->prepare(
        "SELECT order_id, order_status, payment_method, payment_status
         FROM orders WHERE order_id = :id AND customer_id = :cid LIMIT 1 FOR UPDATE"
    );
    $orderStmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $orderStmt->bindValue(':cid', $customer_id, PDO::PARAM_INT);
    $orderStmt->execute();
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    if (!in_array($order['order_status'], CANCELLABLE_STATUSES, true)) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'This order can no longer be cancelled — it is already ' . $order['order_status'] . '.']);
        exit;
    }

    $itemsStmt = $conn->prepare(
        "SELECT product_id, selected_size, quantity FROM order_items WHERE order_id = :id"
    );
    $itemsStmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        if ($item['selected_size']) {
            $conn->prepare(
                "UPDATE product_sizes SET stock_quantity = stock_quantity + :qty
                 WHERE product_id = :pid AND size_label = :size"
            )->execute([
                ':qty'  => $item['quantity'],
                ':pid'  => $item['product_id'],
                ':size' => $item['selected_size'],
            ]);
            $conn->prepare(
                "UPDATE products SET stock_quantity = (SELECT COALESCE(SUM(stock_quantity),0) FROM product_sizes WHERE product_id = :pid)
                 WHERE product_id = :pid2"
            )->execute([':pid' => $item['product_id'], ':pid2' => $item['product_id']]);
        } else {
            $conn->prepare(
                "UPDATE products SET stock_quantity = stock_quantity + :qty WHERE product_id = :pid"
            )->execute([':qty' => $item['quantity'], ':pid' => $item['product_id']]);
        }
    }

    $conn->prepare(
        "UPDATE orders SET order_status = 'cancelled', cancelled_at = NOW() WHERE order_id = :id"
    )->execute([':id' => $order_id]);

    $conn->prepare(
        "UPDATE order_items SET item_status = 'cancelled' WHERE order_id = :id"
    )->execute([':id' => $order_id]);

    $conn->prepare(
        "INSERT INTO order_status_history (order_id, status_from, status_to, changed_by, change_note)
         VALUES (:id, :from, 'cancelled', :by, 'Cancelled by customer')"
    )->execute([':id' => $order_id, ':from' => $order['order_status'], ':by' => $customer_id]);

    $conn->commit();

    $message = 'Order cancelled.';
    if ($order['payment_method'] === 'online' && $order['payment_status'] === 'paid') {
        $message .= ' Your payment will be refunded by our support team.';
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

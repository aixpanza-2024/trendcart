<?php
/**
 * Admin Mark Payment as Paid API
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_SESSION['logged_in']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['payment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get payment details
    $stmt = $conn->prepare("SELECT * FROM shop_payments WHERE payment_id = :id");
    $stmt->bindParam(':id', $data['payment_id']);
    $stmt->execute();
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('Payment record not found');
    }

    if ($payment['payment_status'] === 'paid') {
        throw new Exception('Payment already marked as paid');
    }

    // Update payment
    $stmt = $conn->prepare("
        UPDATE shop_payments SET
            payment_status = 'paid',
            paid_amount = payable_amount,
            payment_method = :method,
            transaction_reference = :ref,
            notes = :notes,
            paid_at = NOW(),
            paid_by = :paid_by
        WHERE payment_id = :id
    ");
    $method = $data['payment_method'] ?? 'bank_transfer';
    $stmt->bindParam(':method', $method);
    $ref = $data['transaction_reference'] ?? '';
    $stmt->bindParam(':ref', $ref);
    $notes = $data['notes'] ?? '';
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':paid_by', $_SESSION['user_id']);
    $stmt->bindParam(':id', $data['payment_id']);
    $stmt->execute();

    // Log activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, description, ip_address)
        VALUES (:admin_id, 'mark_paid', 'payment', :entity_id, :desc, :ip)
    ");
    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $stmt->bindParam(':entity_id', $data['payment_id']);
    $desc = 'Marked payment #' . $data['payment_id'] . ' as paid - ' . $payment['payable_amount'];
    $stmt->bindParam(':desc', $desc);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt->bindParam(':ip', $ip);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Payment marked as paid']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

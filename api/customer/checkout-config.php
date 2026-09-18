<?php
/**
 * Customer - Checkout Config
 * Returns handling_fee, first_order_free_delivery setting,
 * and whether this customer's current order will be their first.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';

try {
    $database = new Database();
    $conn     = $database->getConnection();

    // Load relevant platform settings
    $stmt = $conn->query(
        "SELECT setting_key, setting_value FROM platform_settings
         WHERE setting_key IN ('handling_fee', 'first_order_free_delivery')"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $handling_fee              = (float)($rows['handling_fee'] ?? 0);
    $first_order_free_delivery = (int)($rows['first_order_free_delivery'] ?? 1) === 1;

    // Check if this customer has placed any previous orders (session or token — optional, not gated)
    $is_first_order = false;
    $authedUser = TokenAuth::getUser($conn);
    if ($authedUser && $authedUser['user_type'] === 'customer') {

        $customer_id = $authedUser['user_id'];
        $oStmt = $conn->prepare(
            "SELECT COUNT(*) FROM orders WHERE customer_id = :id AND order_status != 'cancelled'"
        );
        $oStmt->bindValue(':id', $customer_id, PDO::PARAM_INT);
        $oStmt->execute();
        $order_count = (int)$oStmt->fetchColumn();
        $is_first_order = ($order_count === 0);
    }

    echo json_encode([
        'success'                   => true,
        'handling_fee'              => $handling_fee,
        'first_order_free_delivery' => $first_order_free_delivery,
        'is_first_order'            => $is_first_order,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success'                   => true,
        'handling_fee'              => 0,
        'first_order_free_delivery' => true,
        'is_first_order'            => false,
    ]);
}
?>

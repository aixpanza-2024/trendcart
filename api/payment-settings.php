<?php
/**
 * Public - Payment Settings API
 * Returns which payment methods are currently enabled on the platform.
 * No authentication required (read-only, non-sensitive).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/RazorpayClient.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query(
        "SELECT setting_key, setting_value FROM platform_settings
         WHERE setting_key IN ('cod_enabled', 'online_payment_enabled')"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $online_payment_enabled = (int)($rows['online_payment_enabled'] ?? 1) === 1;

    // The key ID is Razorpay's public identifier (used to open the Checkout
    // popup) — safe to expose, unlike the secret, which never leaves the server.
    $razorpay_key_id = null;
    try {
        $razorpay_key_id = (new RazorpayClient())->getKeyId();
    } catch (Exception $e) {
        // Not configured on this server — leave null, razorpay_enabled below covers it.
    }

    echo json_encode([
        'success' => true,
        'cod_enabled'            => (int)($rows['cod_enabled'] ?? 1) === 1,
        'online_payment_enabled' => $online_payment_enabled,
        'razorpay_enabled'       => $online_payment_enabled && $razorpay_key_id !== null,
        'razorpay_key_id'        => $razorpay_key_id,
        'currency'               => 'INR',
    ]);

} catch (Exception $e) {
    // Default to enabled on error so checkout doesn't break unexpectedly
    echo json_encode([
        'success' => true, 'cod_enabled' => true, 'online_payment_enabled' => true,
        'razorpay_enabled' => false, 'razorpay_key_id' => null, 'currency' => 'INR',
    ]);
}
?>

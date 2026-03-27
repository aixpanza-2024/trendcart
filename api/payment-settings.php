<?php
/**
 * Public - Payment Settings API
 * Returns which payment methods are currently enabled on the platform.
 * No authentication required (read-only, non-sensitive).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query(
        "SELECT setting_key, setting_value FROM platform_settings
         WHERE setting_key IN ('cod_enabled')"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'success' => true,
        'cod_enabled' => (int)($rows['cod_enabled'] ?? 1) === 1,
    ]);

} catch (Exception $e) {
    // Default to enabled on error so checkout doesn't break unexpectedly
    echo json_encode(['success' => true, 'cod_enabled' => true]);
}
?>

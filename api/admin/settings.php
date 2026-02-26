<?php
/**
 * Admin - Platform Settings API
 * GET  → return all settings
 * POST → update a setting by key
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->query("SELECT setting_key, setting_value, setting_type, description FROM platform_settings ORDER BY setting_id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = [
                'value'       => $r['setting_value'],
                'type'        => $r['setting_type'],
                'description' => $r['description']
            ];
        }
        echo json_encode(['success' => true, 'data' => $settings]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // Accept a single key/value or an array of {key, value} pairs
        if (isset($body['key']) && isset($body['value'])) {
            $updates = [['key' => $body['key'], 'value' => $body['value']]];
        } elseif (isset($body['settings']) && is_array($body['settings'])) {
            $updates = $body['settings'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid payload']);
            exit();
        }

        $stmt = $conn->prepare(
            "UPDATE platform_settings SET setting_value = :val, updated_by = :uid WHERE setting_key = :key"
        );

        foreach ($updates as $item) {
            $stmt->bindValue(':key', $item['key']);
            $stmt->bindValue(':val', (string)$item['value']);
            $stmt->bindValue(':uid', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
            $stmt->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Settings saved']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

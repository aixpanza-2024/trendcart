<?php
/**
 * Admin Generate Payment Records (Daily or Weekly)
 * Creates payment entries for all shops based on their DELIVERED sales
 * for the requested period.
 *
 * POST body (JSON): { "period": "daily" | "weekly" }
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

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Read period type from request body
    $body   = json_decode(file_get_contents('php://input'), true);
    $period = isset($body['period']) ? trim($body['period']) : 'weekly';
    if (!in_array($period, ['daily', 'weekly'])) $period = 'weekly';

    // Determine date range
    if ($period === 'daily') {
        $period_start = date('Y-m-d');
        $period_end   = date('Y-m-d');
    } else {
        $period_start = date('Y-m-d', strtotime('monday this week'));
        $period_end   = date('Y-m-d', strtotime('sunday this week'));
    }

    // Get platform commission rate
    $stmt = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'commission_rate'");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $commission_rate = $row ? floatval($row['setting_value']) : 10.0;

    // Check if records already exist for this period
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM shop_payments
        WHERE period_type = :ptype AND period_start = :start AND period_end = :end
    ");
    $stmt->bindValue(':ptype', $period);
    $stmt->bindValue(':start', $period_start);
    $stmt->bindValue(':end',   $period_end);
    $stmt->execute();
    $existing = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($existing > 0) {
        echo json_encode([
            'success' => false,
            'message' => ucfirst($period) . ' payment records already exist for ' . $period_start
                         . ($period === 'weekly' ? ' to ' . $period_end : '') . '. Delete existing records first.'
        ]);
        exit();
    }

    // Get all shops with delivered sales for this period
    $stmt = $conn->prepare("
        SELECT
            s.shop_id,
            COALESCE(SUM(oi.subtotal), 0) AS period_sales
        FROM shops s
        LEFT JOIN order_items oi ON s.shop_id = oi.shop_id
            AND oi.item_status = 'delivered'
        LEFT JOIN orders o ON oi.order_id = o.order_id
            AND DATE(o.order_date) >= :start
            AND DATE(o.order_date) <= :end
        GROUP BY s.shop_id
        HAVING period_sales > 0
    ");
    $stmt->bindValue(':start', $period_start);
    $stmt->bindValue(':end',   $period_end);
    $stmt->execute();
    $shop_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $created = 0;

    foreach ($shop_sales as $shop) {
        $total_sales       = floatval($shop['period_sales']);
        $commission_amount = round($total_sales * ($commission_rate / 100), 2);
        $payable_amount    = round($total_sales - $commission_amount, 2);

        $ins = $conn->prepare("
            INSERT INTO shop_payments
                (shop_id, period_type, period_start, period_end,
                 total_sales, commission_rate, commission_amount, payable_amount)
            VALUES
                (:shop_id, :ptype, :start, :end,
                 :sales, :rate, :commission, :payable)
        ");
        $ins->bindValue(':shop_id',    $shop['shop_id'], PDO::PARAM_INT);
        $ins->bindValue(':ptype',      $period);
        $ins->bindValue(':start',      $period_start);
        $ins->bindValue(':end',        $period_end);
        $ins->bindValue(':sales',      $total_sales);
        $ins->bindValue(':rate',       $commission_rate);
        $ins->bindValue(':commission', $commission_amount);
        $ins->bindValue(':payable',    $payable_amount);
        $ins->execute();
        $created++;
    }

    $range = ($period === 'weekly') ? "$period_start to $period_end" : $period_start;
    echo json_encode([
        'success' => true,
        'message' => "Generated $created $period payment record(s) for $range (delivered orders only)"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

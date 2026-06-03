<?php
/**
 * Admin Generate Payment Records (Daily or Weekly)
 * Creates payment entries for all shops based on their DELIVERED sales
 * for the requested period.
 *
 * POST body (JSON): { "period": "daily" | "weekly" }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
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
    $period = isset($body['period']) ? trim($body['period']) : 'daily';
    if (!in_array($period, ['daily', 'range'])) $period = 'daily';

    // Determine date range
    if ($period === 'daily') {
        $period_start = date('Y-m-d');
        $period_end   = date('Y-m-d');
    } else {
        // Custom date range — validate both dates
        $period_start = isset($body['date_from']) ? trim($body['date_from']) : '';
        $period_end   = isset($body['date_to'])   ? trim($body['date_to'])   : '';

        if (!$period_start || !$period_end || !strtotime($period_start) || !strtotime($period_end)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date range provided.']);
            exit();
        }
        if ($period_start > $period_end) {
            echo json_encode(['success' => false, 'message' => 'From date cannot be after To date.']);
            exit();
        }
        if ($period_end >= date('Y-m-d')) {
            echo json_encode(['success' => false, 'message' => 'To date must be before today.']);
            exit();
        }
        $diffDays = (strtotime($period_end) - strtotime($period_start)) / 86400 + 1;
        if ($diffDays > 90) {
            echo json_encode(['success' => false, 'message' => 'Maximum date range is 90 days.']);
            exit();
        }
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

    // Get all shops with delivered sales for this period.
    // Use orders.delivered_at when available (accurate), fall back to order_date for old orders.
    // Exclude items already counted in any previous payout period for the same shop.
    $stmt = $conn->prepare("
        SELECT
            s.shop_id,
            SUM(oi.subtotal) AS period_sales
        FROM shops s
        INNER JOIN order_items oi ON s.shop_id = oi.shop_id AND oi.item_status = 'delivered'
        INNER JOIN orders o ON oi.order_id = o.order_id
        WHERE DATE(COALESCE(o.delivered_at, o.order_date)) >= :start
          AND DATE(COALESCE(o.delivered_at, o.order_date)) <= :end
          AND NOT EXISTS (
              SELECT 1 FROM shop_payments sp
              WHERE sp.shop_id = oi.shop_id
                AND sp.period_start <= DATE(COALESCE(o.delivered_at, o.order_date))
                AND sp.period_end   >= DATE(COALESCE(o.delivered_at, o.order_date))
          )
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

    $range = ($period_start === $period_end) ? $period_start : "$period_start to $period_end";
    echo json_encode([
        'success' => true,
        'message' => "Generated $created payment record(s) for $range (delivered orders only)"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

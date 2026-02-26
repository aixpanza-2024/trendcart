<?php
/**
 * Shop - Finance API
 * Returns earnings summary, commission breakdown, items sold list, payment history
 * All amounts use commission_rate from platform_settings
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'shop') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid shop session']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Resolve actual shop_id from session user_id
    $sr = $conn->prepare("SELECT shop_id FROM shops WHERE user_id = :uid LIMIT 1");
    $sr->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $sr->execute();
    $shopRow = $sr->fetch(PDO::FETCH_ASSOC);
    if (!$shopRow) {
        echo json_encode(['success' => false, 'message' => 'Shop not found']);
        exit();
    }
    $shop_id = (int)$shopRow['shop_id'];

    // Get commission rate from platform_settings
    $cStmt = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'commission_rate' LIMIT 1");
    $cStmt->execute();
    $row = $cStmt->fetch(PDO::FETCH_ASSOC);
    $commission_rate = $row ? (float)$row['setting_value'] : 10.0;

    // Date filters (optional)
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
    $status    = isset($_GET['status'])    ? trim($_GET['status'])    : '';

    // ── Items Sold ──────────────────────────────────────────────────
    $iSql = "SELECT oi.order_item_id, oi.order_id, oi.product_name, oi.quantity,
                    oi.price, oi.subtotal, oi.item_status, oi.created_at,
                    o.order_number, o.order_date, o.shipping_name, o.shipping_city
             FROM order_items oi
             INNER JOIN orders o ON oi.order_id = o.order_id
             WHERE oi.shop_id = :shop_id
               AND o.order_status NOT IN ('cancelled','refunded')";

    $iParams = [':shop_id' => $shop_id];

    if ($date_from !== '') {
        $iSql .= " AND DATE(o.order_date) >= :date_from";
        $iParams[':date_from'] = $date_from;
    }
    if ($date_to !== '') {
        $iSql .= " AND DATE(o.order_date) <= :date_to";
        $iParams[':date_to'] = $date_to;
    }
    if ($status !== '') {
        $iSql .= " AND oi.item_status = :status";
        $iParams[':status'] = $status;
    }

    $iSql .= " ORDER BY o.order_date DESC LIMIT 500";

    $iStmt = $conn->prepare($iSql);
    foreach ($iParams as $k => $v) {
        if ($k === ':shop_id') {
            $iStmt->bindValue($k, $v, PDO::PARAM_INT);
        } else {
            $iStmt->bindValue($k, $v);
        }
    }
    $iStmt->execute();
    $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute commission on each item
    foreach ($items as &$item) {
        $item['commission_amount'] = round($item['subtotal'] * $commission_rate / 100, 2);
        $item['net_amount']        = round($item['subtotal'] - $item['commission_amount'], 2);
    }
    unset($item);

    // ── Summary (all-time, delivered orders only) ────────────────────
    $sStmt = $conn->prepare(
        "SELECT COALESCE(SUM(oi.subtotal), 0) AS gross_sales,
                COUNT(DISTINCT oi.order_id)   AS total_orders,
                COUNT(oi.order_item_id)        AS total_items
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.order_id
         WHERE oi.shop_id = :sid
           AND oi.item_status = 'delivered'"
    );
    $sStmt->bindValue(':sid', $shop_id, PDO::PARAM_INT);
    $sStmt->execute();
    $summary = $sStmt->fetch(PDO::FETCH_ASSOC);

    $gross_sales      = (float)$summary['gross_sales'];
    $commission_total = round($gross_sales * $commission_rate / 100, 2);
    $net_earnings     = round($gross_sales - $commission_total, 2);

    // ── Payment History ──────────────────────────────────────────────
    $pStmt = $conn->prepare(
        "SELECT payment_id, period_type, period_start, period_end,
                total_sales, commission_rate, commission_amount,
                payable_amount, payment_status, paid_amount,
                payment_method, paid_at, notes, created_at
         FROM shop_payments
         WHERE shop_id = :sid
         ORDER BY created_at DESC LIMIT 50"
    );
    $pStmt->bindValue(':sid', $shop_id, PDO::PARAM_INT);
    $pStmt->execute();
    $payments = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'          => true,
        'commission_rate'  => $commission_rate,
        'summary' => [
            'gross_sales'      => $gross_sales,
            'commission_total' => $commission_total,
            'net_earnings'     => $net_earnings,
            'total_orders'     => (int)$summary['total_orders'],
            'total_items'      => (int)$summary['total_items'],
        ],
        'items'    => $items,
        'payments' => $payments
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

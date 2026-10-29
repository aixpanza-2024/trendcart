<?php
/**
 * Customer - Check the cart before checkout
 * POST: { items: [{ id, size?, color? }] }
 *
 * Runs the same product / colour / size checks as checkout (OrderService),
 * so the cart page can point at the exact line to fix instead of the
 * customer finding out at checkout. Read-only apart from ProductVariants::ensure.
 * Public: it only looks at products, not at who is asking.
 *
 * Response: { success, all_ok, data: [{ index, status, need, message }] }
 *   status: ok | needs_options (need = ['colour','size'] subset) | unavailable
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/ProductVariants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$items = is_array($input['items'] ?? null) ? array_slice($input['items'], 0, 100) : null;
if ($items === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'items is required']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $pStmt = $conn->prepare(
        "SELECT p.product_name FROM products p
         INNER JOIN shops s ON p.shop_id = s.shop_id
         WHERE p.product_id = :pid AND p.product_status = 'active' AND s.shop_status = 'open'
         LIMIT 1"
    );

    $results = [];
    foreach ($items as $i => $item) {
        $product_id = (int)($item['id'] ?? 0);
        $size  = isset($item['size'])  && $item['size']  !== null ? trim((string)$item['size'])  : null;
        $color = isset($item['color']) && $item['color'] !== null ? trim((string)$item['color']) : null;

        $pStmt->execute([':pid' => $product_id]);
        $name = $product_id > 0 ? $pStmt->fetchColumn() : false;
        $pStmt->closeCursor();

        if ($name === false) {
            $results[] = ['index' => $i, 'status' => 'unavailable', 'need' => [],
                          'message' => 'This product is no longer available. Please remove it.'];
            continue;
        }

        ProductVariants::ensure($conn, $product_id);
        if (ProductVariants::resolve($conn, $product_id, $size, $color)) {
            $results[] = ['index' => $i, 'status' => 'ok', 'need' => [], 'message' => ''];
            continue;
        }

        $need = ProductVariants::missingOptions($conn, $product_id, $size, $color);
        $results[] = $need
            ? ['index' => $i, 'status' => 'needs_options', 'need' => $need,
               'message' => 'Please choose a ' . implode(' and ', $need) . '.']
            : ['index' => $i, 'status' => 'unavailable', 'need' => [],
               'message' => 'This option is no longer available. Please remove it and choose again.'];
    }

    $all_ok = !array_filter($results, fn($r) => $r['status'] !== 'ok');
    echo json_encode(['success' => true, 'all_ok' => $all_ok, 'data' => $results]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

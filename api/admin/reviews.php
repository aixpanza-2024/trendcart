<?php
/**
 * Admin Reviews API
 * GET  — list reviews with filters
 * POST — action: approve | hide | delete
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    /* ── GET: list reviews ────────────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $status  = $_GET['status']  ?? '';   // approved | hidden | all
        $search  = $_GET['search']  ?? '';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = 20;
        $offset  = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];

        if ($status === 'approved') {
            $where[] = 'pr.is_approved = 1';
        } elseif ($status === 'hidden') {
            $where[] = 'pr.is_approved = 0';
        }

        if ($search !== '') {
            $where[] = '(p.product_name LIKE :search OR u.full_name LIKE :search OR pr.review_text LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSQL = implode(' AND ', $where);

        // Total count
        $countStmt = $conn->prepare("
            SELECT COUNT(*) FROM product_reviews pr
            JOIN products p ON pr.product_id = p.product_id
            JOIN users u ON pr.user_id = u.user_id
            WHERE $whereSQL
        ");
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Reviews
        $stmt = $conn->prepare("
            SELECT pr.review_id, pr.rating, pr.review_title, pr.review_text,
                   pr.is_approved, pr.is_verified_purchase, pr.helpful_count, pr.created_at,
                   p.product_id, p.product_name,
                   u.user_id, u.full_name AS customer_name, u.email AS customer_email,
                   sp.shop_name
            FROM product_reviews pr
            JOIN products p  ON pr.product_id = p.product_id
            JOIN users u     ON pr.user_id = u.user_id
            LEFT JOIN shop_profiles sp ON p.shop_id = sp.shop_id
            WHERE $whereSQL
            ORDER BY pr.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary counts
        $sumStmt = $conn->query("
            SELECT
                COUNT(*) AS total,
                SUM(is_approved = 1) AS approved,
                SUM(is_approved = 0) AS hidden,
                ROUND(AVG(rating), 1) AS avg_rating
            FROM product_reviews
        ");
        $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'  => true,
            'data'     => $reviews,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int)ceil($total / $limit),
            'summary'  => $summary,
        ]);
        exit();
    }

    /* ── POST: action ─────────────────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action']    ?? '';
        $id     = (int)($body['review_id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Review ID required']);
            exit();
        }

        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE product_reviews SET is_approved = 1 WHERE review_id = :id");
        } elseif ($action === 'hide') {
            $stmt = $conn->prepare("UPDATE product_reviews SET is_approved = 0 WHERE review_id = :id");
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM product_reviews WHERE review_id = :id");
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => ucfirst($action) . 'd successfully']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

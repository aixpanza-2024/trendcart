<?php
/**
 * Admin Banners API — GET list / POST create / PUT update / DELETE remove
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$database = new Database();
$conn     = $database->getConnection();
$method   = $_SERVER['REQUEST_METHOD'];

try {

    /* ── GET: list all banners ── */
    if ($method === 'GET') {
        $stmt = $conn->query("SELECT * FROM banners ORDER BY display_order ASC, banner_id ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit();
    }

    /* ── POST: create banner ── */
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $image_url     = trim($input['image_url'] ?? '');
        $title         = trim($input['title'] ?? '');
        $subtitle      = trim($input['subtitle'] ?? '');
        $link_url      = trim($input['link_url'] ?? '');
        $display_order = (int)($input['display_order'] ?? 0);
        $is_active     = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        if (!$image_url) {
            echo json_encode(['success' => false, 'message' => 'Image is required']);
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO banners (title, subtitle, image_url, link_url, display_order, is_active)
            VALUES (:title, :subtitle, :image_url, :link_url, :display_order, :is_active)
        ");
        $stmt->bindValue(':title',         $title ?: null);
        $stmt->bindValue(':subtitle',      $subtitle ?: null);
        $stmt->bindValue(':image_url',     $image_url);
        $stmt->bindValue(':link_url',      $link_url ?: null);
        $stmt->bindValue(':display_order', $display_order, PDO::PARAM_INT);
        $stmt->bindValue(':is_active',     $is_active, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Banner created', 'banner_id' => $conn->lastInsertId()]);
        exit();
    }

    /* ── PUT: update banner ── */
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $banner_id     = (int)($input['banner_id'] ?? 0);
        $title         = trim($input['title'] ?? '');
        $subtitle      = trim($input['subtitle'] ?? '');
        $image_url     = trim($input['image_url'] ?? '');
        $link_url      = trim($input['link_url'] ?? '');
        $display_order = (int)($input['display_order'] ?? 0);
        $is_active     = (int)($input['is_active'] ?? 1);

        if ($banner_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid banner ID']);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE banners SET
                title = :title,
                subtitle = :subtitle,
                image_url = :image_url,
                link_url = :link_url,
                display_order = :display_order,
                is_active = :is_active
            WHERE banner_id = :id
        ");
        $stmt->bindValue(':title',         $title ?: null);
        $stmt->bindValue(':subtitle',      $subtitle ?: null);
        $stmt->bindValue(':image_url',     $image_url);
        $stmt->bindValue(':link_url',      $link_url ?: null);
        $stmt->bindValue(':display_order', $display_order, PDO::PARAM_INT);
        $stmt->bindValue(':is_active',     $is_active, PDO::PARAM_INT);
        $stmt->bindValue(':id',            $banner_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Banner updated']);
        exit();
    }

    /* ── DELETE: remove banner ── */
    if ($method === 'DELETE') {
        $input     = json_decode(file_get_contents('php://input'), true) ?? [];
        $banner_id = (int)($input['banner_id'] ?? $_GET['banner_id'] ?? 0);

        if ($banner_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid banner ID']);
            exit();
        }

        // Get image path to delete file
        $stmt = $conn->prepare("SELECT image_url FROM banners WHERE banner_id = :id");
        $stmt->bindValue(':id', $banner_id, PDO::PARAM_INT);
        $stmt->execute();
        $banner = $stmt->fetch();

        if ($banner && $banner['image_url']) {
            $filePath = __DIR__ . '/../../' . ltrim($banner['image_url'], '/');
            if (file_exists($filePath)) @unlink($filePath);
        }

        $stmt = $conn->prepare("DELETE FROM banners WHERE banner_id = :id");
        $stmt->bindValue(':id', $banner_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Banner deleted']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

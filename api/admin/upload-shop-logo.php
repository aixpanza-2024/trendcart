<?php
/**
 * Admin Upload Shop Logo API
 * POST: accepts logo file + shop_id, stores in uploads/shops/, updates shop_logo in DB
 */

header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$shop_id = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : 0;
if ($shop_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid shop ID']);
    exit();
}

if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['logo']['error'] ?? 'no file';
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error: ' . $err]);
    exit();
}

$file = $_FILES['logo'];

// Validate MIME type
$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WebP images are allowed']);
    exit();
}

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image must be under 2MB']);
    exit();
}

try {
    $database = new Database();
    $conn     = $database->getConnection();

    // Verify shop exists and get current logo
    $stmt = $conn->prepare("SELECT shop_id, shop_logo FROM shops WHERE shop_id = :id LIMIT 1");
    $stmt->bindValue(':id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        echo json_encode(['success' => false, 'message' => 'Shop not found']);
        exit();
    }

    $ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext       = $ext_map[$mime];
    $filename  = 'shop_' . $shop_id . '_' . time() . '.' . $ext;
    $uploadDir = '../../uploads/shops/';
    $destPath  = $uploadDir . $filename;

    // Ensure directory exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Delete old logo file if it exists
    if (!empty($shop['shop_logo'])) {
        $oldFile = $uploadDir . basename($shop['shop_logo']);
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save image file']);
        exit();
    }

    $logoPath = '/uploads/shops/' . $filename;

    $stmt = $conn->prepare("UPDATE shops SET shop_logo = :logo WHERE shop_id = :id");
    $stmt->bindValue(':logo', $logoPath);
    $stmt->bindValue(':id',   $shop_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Logo uploaded successfully', 'logo_url' => $logoPath]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

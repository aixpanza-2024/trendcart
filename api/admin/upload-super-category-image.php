<?php
/**
 * Admin Upload Super Category Image
 * POST: accepts image file, stores in uploads/super-categories/, returns URL
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';

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

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['image'];

$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WebP or GIF images allowed']);
    exit();
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image must be under 5MB']);
    exit();
}

$ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$ext       = $ext_map[$mime];
$filename  = 'supercat_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
$uploadDir = __DIR__ . '/../../uploads/super-categories/';
$destPath  = $uploadDir . $filename;

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save image']);
    exit();
}

echo json_encode(['success' => true, 'image_url' => '/uploads/super-categories/' . $filename]);
?>

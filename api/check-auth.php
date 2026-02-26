<?php
/**
 * Check Authentication API Endpoint
 * GET /api/check-auth.php - Check if user is logged in
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'controllers/AuthController.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Create controller instance and check auth
$authController = new AuthController();
$authController->checkAuth();
?>

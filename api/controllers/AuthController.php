<?php
/**
 * Authentication Controller
 * Three-Tier Architecture - Business Logic Layer
 * Handles authentication logic
 */

require_once '../api/config/database.php';
require_once '../api/models/User.php';
require_once '../api/utils/OTPManager.php';
require_once '../api/utils/EmailManager.php';

class AuthController {
    private $db;
    private $user;
    private $otpManager;
    private $emailManager;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();

        if ($this->db === null) {
            $this->sendResponse(500, false, "Database connection failed");
            exit;
        }

        $this->user = new User($this->db);
        $this->otpManager = new OTPManager($this->db);
        $this->emailManager = new EmailManager();
    }

    /**
     * Handle user registration (Step 1: Send OTP)
     */
    public function register() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));

        // Validate inputs
        if (!isset($data->email) || !isset($data->full_name) || !isset($data->phone) || !isset($data->user_type)) {
            $this->sendResponse(400, false, "Missing required fields");
            return;
        }

        // Validate email format
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(400, false, "Invalid email format");
            return;
        }

        // Validate phone format (10 digits)
        if (!preg_match('/^[6-9]\d{9}$/', $data->phone)) {
            $this->sendResponse(400, false, "Invalid phone number. Must be 10 digits starting with 6-9");
            return;
        }

        // Validate user type
        $valid_types = ['customer', 'shop', 'admin', 'delivery_boy'];
        if (!in_array($data->user_type, $valid_types)) {
            $this->sendResponse(400, false, "Invalid user type");
            return;
        }

        // Check if email already exists
        if ($this->user->emailExists($data->email)) {
            $this->sendResponse(409, false, "Email already registered");
            return;
        }

        // Check if phone already exists
        if ($this->user->phoneExists($data->phone)) {
            $this->sendResponse(409, false, "Phone number already registered");
            return;
        }

        // Generate OTP
        $otp = $this->otpManager->generateOTP();

        // Save OTP to database
        if ($this->otpManager->saveOTP(null, $data->email, $otp, 'registration')) {
            // Try to send OTP via email
            $email_sent = $this->emailManager->sendOTPEmail($data->email, $data->full_name, $otp, 'registration');

            $this->sendResponse(200, true, "OTP sent successfully to your email", [
                'email'      => $data->email,
                'email_sent' => $email_sent
            ]);
        } else {
            $this->sendResponse(500, false, "Failed to generate OTP");
        }
    }

    /**
     * Verify OTP and complete registration (Step 2)
     * All registration data is passed directly in the request body (no session dependency).
     */
    public function verifyRegistrationOTP() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));

        // Validate inputs
        if (!isset($data->email) || !isset($data->otp)) {
            $this->sendResponse(400, false, "Missing email or OTP");
            return;
        }

        if (!isset($data->full_name) || !isset($data->phone) || !isset($data->user_type)) {
            $this->sendResponse(400, false, "Missing registration fields");
            return;
        }

        $email     = $data->email;
        $full_name = trim($data->full_name);
        $phone     = trim($data->phone);
        $user_type = $data->user_type;
        $shop_name = isset($data->shop_name) ? $data->shop_name : null;

        // Validate user type
        $valid_types = ['customer', 'shop', 'admin', 'delivery_boy'];
        if (!in_array($user_type, $valid_types)) {
            $this->sendResponse(400, false, "Invalid user type");
            return;
        }

        // Verify OTP
        $otp_result = $this->otpManager->verifyOTP($email, $data->otp, 'registration');

        if ($otp_result) {
            // Double-check email is still not taken (edge case: registered between OTP send and verify)
            if ($this->user->emailExists($email)) {
                $this->sendResponse(409, false, "Email already registered");
                return;
            }

            // Create user
            $this->user->email       = $email;
            $this->user->full_name   = $full_name;
            $this->user->phone       = $phone;
            $this->user->user_type   = $user_type;
            $this->user->is_verified = true;

            $user_id = $this->user->create();

            if ($user_id) {
                // Handle optional shop photo upload
                $shop_image_path = null;
                if ($user_type === 'shop' && isset($data->shop_image) && !empty($data->shop_image)) {
                    $shop_image_path = $this->saveShopPhoto($data->shop_image, $user_id);
                }

                // Create profile based on user type
                if ($user_type === 'customer') {
                    $this->user->createCustomerProfile($user_id);
                } elseif ($user_type === 'shop' && $shop_name) {
                    $this->user->createShopProfile($user_id, $shop_name, $shop_image_path);
                }

                // Create session
                $this->createUserSession($user_id, $user_type);

                // Get user data
                $userData = $this->user->getUserWithProfile($user_id);

                $this->sendResponse(201, true, "Registration successful", [
                    'user'     => $userData,
                    'redirect' => $this->getRedirectUrl($user_type)
                ]);
            } else {
                $this->sendResponse(500, false, "Failed to create user account");
            }
        } else {
            $this->sendResponse(400, false, "Invalid or expired OTP");
        }
    }

    /**
     * Save base64 shop photo to disk, return relative path or null on failure
     */
    private function saveShopPhoto($base64_data, $user_id) {
        try {
            // Strip data URI header (e.g. "data:image/jpeg;base64,")
            $image_data = preg_replace('#^data:image/\w+;base64,#i', '', $base64_data);
            $binary = base64_decode($image_data);

            if (!$binary) return null;

            // Validate it is actually an image
            $img_info = @getimagesizefromstring($binary);
            if (!$img_info) return null;

            $ext = image_type_to_extension($img_info[2], false);
            if (!in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'webp'])) return null;
            $ext = ($ext === 'jpeg') ? 'jpg' : $ext;

            $upload_dir = __DIR__ . '/../../uploads/shops/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $filename = 'shop_' . $user_id . '_' . time() . '.' . $ext;
            file_put_contents($upload_dir . $filename, $binary);

            return 'uploads/shops/' . $filename;
        } catch (Exception $e) {
            error_log("Save shop photo error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle user login (Step 1: Send OTP)
     */
    public function login() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));

        // Validate inputs
        if (!isset($data->email)) {
            $this->sendResponse(400, false, "Email is required");
            return;
        }

        // Validate email format
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(400, false, "Invalid email format");
            return;
        }

        // Check if user exists
        $user_data = $this->user->getUserByEmail($data->email);

        if (!$user_data) {
            $this->sendResponse(404, false, "User not found. Please register first");
            return;
        }

        // Check if user is active
        if (!$user_data['is_active']) {
            $this->sendResponse(403, false, "Account is deactivated. Please contact support");
            return;
        }

        // Generate OTP
        $otp = $this->otpManager->generateOTP();

        // Save OTP to database
        if ($this->otpManager->saveOTP($user_data['user_id'], $user_data['email'], $otp, 'login')) {
            // Try to send OTP via email
            $email_sent = $this->emailManager->sendOTPEmail($user_data['email'], $user_data['full_name'], $otp, 'login');

            $this->sendResponse(200, true, "OTP sent successfully to your email", [
                'email'      => $user_data['email'],
                'email_sent' => $email_sent
            ]);
        } else {
            $this->sendResponse(500, false, "Failed to generate OTP");
        }
    }

    /**
     * Verify OTP and complete login (Step 2)
     */
    public function verifyLoginOTP() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));

        // Validate inputs
        if (!isset($data->email) || !isset($data->otp)) {
            $this->sendResponse(400, false, "Missing email or OTP");
            return;
        }

        // Verify OTP
        $otp_result = $this->otpManager->verifyOTP($data->email, $data->otp, 'login');

        if ($otp_result) {
            $user_data = $this->user->getUserByEmail($data->email);

            if ($user_data) {
                // Update last login
                $this->user->updateLastLogin($user_data['user_id']);

                // Create session (store user_type so admin APIs can verify it)
                $this->createUserSession($user_data['user_id'], $user_data['user_type']);

                // Get complete user data with profile
                $userData = $this->user->getUserWithProfile($user_data['user_id']);

                $this->sendResponse(200, true, "Login successful", [
                    'user' => $userData,
                    'redirect' => $this->getRedirectUrl($userData['user_type'])
                ]);
            } else {
                $this->sendResponse(404, false, "User not found");
            }
        } else {
            $this->sendResponse(400, false, "Invalid or expired OTP");
        }
    }

    /**
     * Create user session
     */
    private function createUserSession($user_id, $user_type = null) {
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user_id;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        if ($user_type) {
            $_SESSION['user_type'] = $user_type;
        }
    }

    /**
     * Get redirect URL based on user type
     */
    private function getRedirectUrl($user_type) {
        switch ($user_type) {
            case 'admin':
                return '../admin/dashboard.html';
            case 'shop':
                return '../shop/dashboard.html';
            case 'delivery_boy':
                return '../delivery/dashboard.html';
            default:
                return '../index.html';
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        session_start();
        session_unset();
        session_destroy();

        $this->sendResponse(200, true, "Logged out successfully");
    }

    /**
     * Check if user is logged in
     */
    public function checkAuth() {
        session_start();

        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            $user_data = $this->user->getUserWithProfile($_SESSION['user_id']);

            $this->sendResponse(200, true, "User is authenticated", [
                'logged_in' => true,
                'user' => $user_data
            ]);
        } else {
            $this->sendResponse(401, false, "Not authenticated", [
                'logged_in' => false
            ]);
        }
    }

    /**
     * Send JSON response
     */
    private function sendResponse($status_code, $success, $message, $data = null) {
        http_response_code($status_code);
        header('Content-Type: application/json');

        $response = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response);
    }
}
?>

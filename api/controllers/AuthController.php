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
require_once '../api/utils/SMSManager.php';
require_once '../api/utils/TokenAuth.php';
require_once '../api/utils/DebugOtp.php';

class AuthController {
    private $db;
    private $user;
    private $otpManager;
    private $emailManager;
    private $smsManager;

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
        $this->smsManager = new SMSManager();
    }

    /**
     * Normalize an Indian mobile number to 10 digits.
     * Accepts spaces/dashes, a +91 / 91 prefix, or a leading 0.
     * @return string|null null when it is not a valid mobile number
     */
    private function normalizePhone($raw) {
        $digits = preg_replace('/\D+/', '', (string)$raw);

        if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
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

            $resp = [
                'email'      => $data->email,
                'email_sent' => $email_sent
            ];
            $dbg = DebugOtp::reveal($data->email, $otp);
            if ($dbg !== null) { $resp['debug_otp'] = $dbg; }

            $this->sendResponse(200, true, "OTP sent successfully to your email", $resp);
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

                // Create session (web) + token (mobile/API clients)
                $this->createUserSession($user_id, $user_type);
                $token = TokenAuth::createToken($this->db, $user_id);

                // Get user data
                $userData = $this->user->getUserWithProfile($user_id);

                $this->sendResponse(201, true, "Registration successful", [
                    'user'     => $userData,
                    'token'    => $token,
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
     * Handle user login/registration (Step 1: Send OTP)
     * Works for both existing and new customers — OTP is sent either way.
     * New customers are auto-created when OTP is verified in verifyLoginOTP().
     */
    public function login() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));

        $email     = isset($data->email) ? strtolower(trim($data->email)) : '';
        $phone_raw = isset($data->phone) ? trim($data->phone) : '';

        if ($email === '' && $phone_raw === '') {
            $this->sendResponse(400, false, "Email or mobile number is required");
            return;
        }

        $is_phone = ($email === '');

        if ($is_phone) {
            $identifier = $this->normalizePhone($phone_raw);
            if ($identifier === null) {
                $this->sendResponse(400, false, "Enter a valid 10-digit mobile number");
                return;
            }
            $user_data = $this->user->getUserByPhone($identifier);
            $echo      = ['phone' => $identifier];
        } else {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->sendResponse(400, false, "Invalid email format");
                return;
            }
            $identifier = $email;
            $user_data  = $this->user->getUserByEmail($email);
            $echo       = ['email' => $email];
        }

        // Block deactivated accounts before sending OTP
        if ($user_data && !$user_data['is_active']) {
            $this->sendResponse(403, false, "Account is deactivated. Please contact support");
            return;
        }

        // Enforce the resend cooldown on the server (the browser timer is only for display).
        // Test servers (DEBUG_SHOW_OTP) skip it so a test login can be repeated straight away.
        $test_mode = DebugOtp::isEnabledFor($identifier);
        $wait = $test_mode ? 0 : $this->otpManager->getResendWaitSeconds($identifier, 'login');
        if ($wait > 0) {
            $this->sendResponse(429, false, "Please wait {$wait} seconds before requesting a new OTP",
                $echo + ['retry_after' => $wait]);
            return;
        }

        // Generate OTP
        $otp = $this->otpManager->generateOTP();

        // Use existing user_id if found, null for new users
        $user_id   = $user_data ? $user_data['user_id'] : null;
        $full_name = $user_data ? $user_data['full_name'] : '';

        // Save OTP to database
        if (!$this->otpManager->saveOTP($user_id, $identifier, $otp, 'login')) {
            $this->sendResponse(500, false, "Failed to generate OTP");
            return;
        }

        if ($is_phone) {
            $resp = $echo + ['resend_after' => OTPManager::RESEND_COOLDOWN_SECONDS];

            $dbg = DebugOtp::reveal($identifier, $otp);
            if ($dbg !== null) {
                // Test server without an SMS gateway: hand the OTP back instead of texting it
                $resp['debug_otp'] = $dbg;
            } elseif (!$this->smsManager->sendOTP($identifier, $otp)) {
                // Drop the unsent OTP so the failed attempt does not start a cooldown
                $this->otpManager->deleteOldOTPs($identifier, 'login');
                $this->sendResponse(502, false, "Could not send the SMS right now. Please try again shortly");
                return;
            }

            $this->sendResponse(200, true, "OTP sent to your mobile number", $resp);
            return;
        }

        // Try to send OTP via email
        $email_sent = $this->emailManager->sendOTPEmail($email, $full_name, $otp, 'login');

        $resp = $echo + ['email_sent' => $email_sent, 'resend_after' => OTPManager::RESEND_COOLDOWN_SECONDS];
        $dbg = DebugOtp::reveal($email, $otp);
        if ($dbg !== null) { $resp['debug_otp'] = $dbg; }

        $this->sendResponse(200, true, "OTP sent to your email", $resp);
    }

    /**
     * Verify OTP and complete login (Step 2)
     * If user doesn't exist yet, auto-creates a customer account with just the email or phone used.
     */
    public function verifyLoginOTP() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));

        $email = isset($data->email) ? strtolower(trim($data->email)) : '';
        $phone = isset($data->phone) ? $this->normalizePhone($data->phone) : null;

        // Validate inputs
        if (!isset($data->otp) || ($email === '' && $phone === null)) {
            $this->sendResponse(400, false, "Missing email/mobile number or OTP");
            return;
        }

        $is_phone   = ($email === '');
        $identifier = $is_phone ? $phone : $email;

        // Verify OTP
        $otp_result = $this->otpManager->verifyOTP($identifier, $data->otp, 'login');

        if (!$otp_result) {
            $this->sendResponse(400, false, "Invalid or expired OTP");
            return;
        }

        $user_data = $is_phone
            ? $this->user->getUserByPhone($phone)
            : $this->user->getUserByEmail($email);

        if (!$user_data) {
            // New user — auto-create customer account with only the email or phone used
            $this->user->full_name   = '';
            $this->user->user_type   = 'customer';
            $this->user->is_verified = true;

            if ($is_phone) {
                $this->user->phone = $phone;
                $user_id = $this->user->createPhoneOnly();
            } else {
                $this->user->email = $email;
                $this->user->phone = null;
                $user_id = $this->user->createEmailOnly();
            }

            if (!$user_id) {
                $this->sendResponse(500, false, "Failed to create account");
                return;
            }

            $this->user->createCustomerProfile($user_id);

            $user_data = $is_phone
                ? $this->user->getUserByPhone($phone)
                : $this->user->getUserByEmail($email);
        }

        // Update last login
        $this->user->updateLastLogin($user_data['user_id']);

        // Create session (web) + token (mobile/API clients)
        $this->createUserSession($user_data['user_id'], $user_data['user_type']);
        $token = TokenAuth::createToken($this->db, $user_data['user_id']);

        // Get complete user data with profile
        $userData = $this->user->getUserWithProfile($user_data['user_id']);

        $this->sendResponse(200, true, "Login successful", [
            'user'     => $userData,
            'token'    => $token,
            'redirect' => $this->getRedirectUrl($userData['user_type'])
        ]);
    }

    /**
     * Create user session
     */
    private function createUserSession($user_id, $user_type = null) {
        $lifetime = 10 * 365 * 24 * 60 * 60; // ~10 years (effectively permanent)
        ini_set('session.gc_maxlifetime', $lifetime);
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
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
        ini_set('session.gc_maxlifetime', 10 * 365 * 24 * 60 * 60);
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        session_unset();
        session_destroy();

        // Also revoke the bearer token, if the client (mobile/API) sent one
        $token = TokenAuth::getBearerToken();
        if ($token) {
            TokenAuth::deleteToken($this->db, $token);
        }

        $this->sendResponse(200, true, "Logged out successfully");
    }

    /**
     * Check if user is logged in
     */
    public function checkAuth() {
        ini_set('session.gc_maxlifetime', 10 * 365 * 24 * 60 * 60);
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        $user = TokenAuth::getUser($this->db); // checks session first, then Authorization: Bearer token

        if ($user) {
            $user_data = $this->user->getUserWithProfile($user['user_id']);

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

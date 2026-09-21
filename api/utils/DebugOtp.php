<?php
/**
 * Test-server helper: lets the API hand back the OTP for specific test emails.
 *
 * Off by default. A server opts in by defining DEBUG_OTP_EMAILS (an array of
 * emails) in its own gitignored api/config/email_config.php. Only those
 * emails ever get their OTP revealed; every other account behaves normally.
 * Production never defines the constant, so nothing changes there.
 */
class DebugOtp {
    public static function reveal($email, $otp) {
        if (!defined('DEBUG_OTP_EMAILS') || !is_array(DEBUG_OTP_EMAILS)) {
            return null;
        }
        $allowed = array_map('strtolower', array_map('trim', DEBUG_OTP_EMAILS));
        return in_array(strtolower(trim($email)), $allowed, true) ? (string)$otp : null;
    }
}
?>

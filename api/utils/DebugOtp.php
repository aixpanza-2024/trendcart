<?php
/**
 * Test-server helper: lets the API hand back the OTP so logins work without email.
 *
 * Off by default. A server opts in by defining, in its own gitignored
 * api/config/email_config.php, either:
 *   define('DEBUG_SHOW_OTP', true);                 // every login/register shows its OTP
 *   define('DEBUG_OTP_EMAILS', ['a@x.com', ...]);   // only these emails
 * Production never defines either constant, so nothing changes there.
 */
class DebugOtp {
    public static function reveal($email, $otp) {
        if (defined('DEBUG_SHOW_OTP') && DEBUG_SHOW_OTP === true) {
            return (string)$otp;
        }
        if (!defined('DEBUG_OTP_EMAILS') || !is_array(DEBUG_OTP_EMAILS)) {
            return null;
        }
        $allowed = array_map('strtolower', array_map('trim', DEBUG_OTP_EMAILS));
        return in_array(strtolower(trim($email)), $allowed, true) ? (string)$otp : null;
    }
}
?>

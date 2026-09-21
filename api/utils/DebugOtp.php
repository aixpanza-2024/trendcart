<?php
/**
 * Test-server helper: lets the API hand back the OTP so logins work without email or SMS.
 *
 * Off by default. A server opts in by defining, in its own gitignored
 * api/config/email_config.php, either:
 *   define('DEBUG_SHOW_OTP', true);                 // every login/register shows its OTP
 *   define('DEBUG_OTP_EMAILS', ['a@x.com', ...]);   // only these emails / 10-digit phone numbers
 * Production never defines either constant, so nothing changes there.
 */
class DebugOtp {
    /**
     * Is this server in test mode for this login identifier (email or 10-digit phone)?
     */
    public static function isEnabledFor($identifier) {
        if (defined('DEBUG_SHOW_OTP') && DEBUG_SHOW_OTP === true) {
            return true;
        }
        if (!defined('DEBUG_OTP_EMAILS') || !is_array(DEBUG_OTP_EMAILS)) {
            return false;
        }
        $allowed = array_map('strtolower', array_map('trim', DEBUG_OTP_EMAILS));
        return in_array(strtolower(trim($identifier)), $allowed, true);
    }

    public static function reveal($email, $otp) {
        return self::isEnabledFor($email) ? (string)$otp : null;
    }
}
?>

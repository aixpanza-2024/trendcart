<?php
/**
 * SMS Manager
 * Sends OTP SMS through the driver chosen in api/config/sms_config.php.
 * Drivers: 'log' (local dev, writes the OTP to the PHP error log) and 'msg91'.
 */

class SMSManager {
    private $config = null;

    public function __construct() {
        $file = __DIR__ . '/../config/sms_config.php';
        if (file_exists($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $this->config = $loaded;
            }
        }
    }

    /**
     * Send an OTP to a 10-digit Indian mobile number.
     * @param string $phone 10-digit number (no country code)
     * @param string $otp
     * @return bool true when the provider accepted the message
     */
    public function sendOTP($phone, $otp) {
        if ($this->config === null) {
            error_log("SMS not configured: copy api/config/sms_config.sample.php to sms_config.php");
            return false;
        }

        $driver = $this->config['driver'] ?? '';

        switch ($driver) {
            case 'log':
                error_log("[SMS log driver] OTP for +91{$phone}: {$otp}");
                return true;
            case 'msg91':
                return $this->sendViaMsg91($phone, $otp);
            default:
                error_log("Unknown SMS driver: {$driver}");
                return false;
        }
    }

    private function sendViaMsg91($phone, $otp) {
        $cfg = $this->config['msg91'] ?? [];

        if (empty($cfg['authkey']) || empty($cfg['template_id'])) {
            error_log("MSG91 authkey / template_id missing in sms_config.php");
            return false;
        }
        if (!function_exists('curl_init')) {
            error_log("PHP curl extension is required for the msg91 SMS driver");
            return false;
        }

        $url = 'https://control.msg91.com/api/v5/otp?' . http_build_query([
            'template_id' => $cfg['template_id'],
            'mobile'      => '91' . $phone,
            'authkey'     => $cfg['authkey'],
            'otp'         => $otp,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            error_log("MSG91 request failed: {$err}");
            return false;
        }

        $json = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($json) && ($json['type'] ?? '') === 'success') {
            return true;
        }

        error_log("MSG91 rejected the OTP SMS (HTTP {$status}): {$body}");
        return false;
    }
}
?>

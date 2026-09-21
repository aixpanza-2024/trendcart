<?php
/**
 * SMS Configuration (sample)
 * -----------------------------------------------
 * Copy this file to api/config/sms_config.php and fill in the values.
 * sms_config.php is listed in .gitignore, so credentials are never pushed to GitHub.
 *
 * driver:
 *   'log'    - local development only. Nothing is sent; the OTP is written to the
 *              PHP error log (XAMPP: xampp/apache/logs/error.log).
 *   'msg91'  - sends the OTP through MSG91's OTP API.
 *              Needs an MSG91 auth key and a DLT-approved OTP template ID
 *              (the template must contain the ##OTP## variable).
 * -----------------------------------------------
 */

return [
    'driver' => 'log',

    'msg91' => [
        'authkey'     => '',
        'template_id' => '',
    ],
];

<?php
/**
 * Firebase Cloud Messaging (HTTP v1) sender.
 *
 * Needs a Firebase service-account JSON at api/config/firebase_service_account.json
 * (gitignored; download from Firebase console → Project settings → Service accounts).
 * Without that file, isConfigured() is false and nothing is sent.
 */
class FcmClient {
    const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private $account = null;
    private $accessToken = null;

    public function __construct() {
        $file = __DIR__ . '/../config/firebase_service_account.json';
        if (is_file($file)) {
            $decoded = json_decode(file_get_contents($file), true);
            if (is_array($decoded) && !empty($decoded['project_id']) && !empty($decoded['private_key']) && !empty($decoded['client_email'])) {
                $this->account = $decoded;
            }
        }
    }

    public function isConfigured() {
        return $this->account !== null;
    }

    /**
     * Send one notification to one device token.
     * @return array{ok:bool, unregistered:bool, error:?string}
     */
    public function sendToToken($token, $title, $body, $data = []) {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'unregistered' => false, 'error' => 'Firebase is not configured'];
        }

        $message = [
            'token'        => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'android'      => ['priority' => 'HIGH'],
        ];
        if (!empty($data)) {
            $message['data'] = array_map('strval', $data);
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($this->account['project_id']) . '/messages:send';
        [$status, $response] = $this->post($url, json_encode(['message' => $message]), [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ]);

        if ($status === 200) {
            return ['ok' => true, 'unregistered' => false, 'error' => null];
        }

        $decoded = json_decode($response, true);
        $errorCode = $decoded['error']['details'][0]['errorCode'] ?? null;
        return [
            'ok'           => false,
            'unregistered' => $errorCode === 'UNREGISTERED',
            'error'        => $decoded['error']['message'] ?? ('HTTP ' . $status),
        ];
    }

    private function getAccessToken() {
        if ($this->accessToken !== null) return $this->accessToken;

        $now = time();
        $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64url(json_encode([
            'iss'   => $this->account['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $this->account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $signature = '';
        if (!openssl_sign("$header.$claims", $signature, $this->account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new Exception('Could not sign Firebase auth request (invalid private key)');
        }
        $assertion = "$header.$claims." . $this->b64url($signature);

        [$status, $response] = $this->post(
            $this->account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        $decoded = json_decode($response, true);
        if ($status !== 200 || empty($decoded['access_token'])) {
            throw new Exception('Firebase auth failed: ' . ($decoded['error_description'] ?? $decoded['error'] ?? "HTTP $status"));
        }

        $this->accessToken = $decoded['access_token'];
        return $this->accessToken;
    }

    private function post($url, $body, $headers) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Firebase request failed: ' . $error);
        }
        return [$status, $response];
    }

    private function b64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
?>

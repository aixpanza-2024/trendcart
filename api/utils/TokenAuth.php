<?php
/**
 * Token-based auth for API clients (mobile app, bots, etc).
 * Runs alongside the existing PHP session auth used by the web app —
 * never replaces it. A request is authenticated if EITHER a valid
 * session OR a valid "Authorization: Bearer <token>" header is present.
 */

class TokenAuth {

    /**
     * Resolve the current request to a logged-in user, checking the
     * session first, then a Bearer token. Returns null if neither applies.
     * @return array{user_id:int,user_type:string}|null
     */
    public static function getUser($conn) {
        if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && !empty($_SESSION['user_id'])) {
            return [
                'user_id'   => (int)$_SESSION['user_id'],
                'user_type' => $_SESSION['user_type'] ?? null,
            ];
        }

        $token = self::getBearerToken();
        if (!$token) return null;

        $stmt = $conn->prepare("
            SELECT s.user_id, u.user_type
            FROM user_sessions s
            INNER JOIN users u ON u.user_id = s.user_id
            WHERE s.session_token = :token AND s.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? ['user_id' => (int)$row['user_id'], 'user_type' => $row['user_type']] : null;
    }

    /**
     * Require a logged-in customer (session or token). Sends a 401 JSON
     * response and exits if the request isn't authenticated as a customer.
     * @return int the customer's user_id
     */
    public static function requireCustomer($conn, $message = 'Please login') {
        $user = self::getUser($conn);
        if (!$user || $user['user_type'] !== 'customer') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        return $user['user_id'];
    }

    /**
     * Create a new token session for a user (called at login/registration).
     * Returns the plaintext token to send back to the client.
     */
    public static function createToken($conn, $user_id, $days = 365) {
        $sessionId = bin2hex(random_bytes(16));
        $token     = bin2hex(random_bytes(32));
        $ip        = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua        = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO user_sessions (session_id, user_id, session_token, ip_address, user_agent, expires_at)
            VALUES (:sid, :uid, :token, :ip, :ua, DATE_ADD(NOW(), INTERVAL :days DAY))
        ");
        $stmt->bindValue(':sid', $sessionId, PDO::PARAM_STR);
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':ua', $ua, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $token;
    }

    /** Delete a token session (logout). Safe to call with no/invalid token. */
    public static function deleteToken($conn, $token) {
        if (!$token) return;
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_token = :token");
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
    }

    public static function getBearerToken() {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if (!$header && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) { $header = $value; break; }
            }
        }

        if (!$header || stripos($header, 'Bearer ') !== 0) return null;
        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }
}
?>

<?php
/**
 * Rate limiter for the OTP endpoints.
 * Stored in the existing login_attempts table (email = "<bucket>:<key>"), so no
 * schema change is needed. Fails open: if the table is missing or a query
 * errors, the request is allowed and the error is logged.
 * Bucket names must not contain "_" or "%" (they are used inside a LIKE).
 */

class RateLimiter {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Seconds the caller must wait before the next allowed attempt; 0 when allowed.
     * Applies both a per-key limit (one email / phone) and a per-IP limit.
     */
    public function secondsUntilAllowed($bucket, $key, $maxPerKey, $maxPerIp, $windowSeconds) {
        try {
            return max(
                $this->waitFor('email = :k', [':k' => "$bucket:$key"], $maxPerKey, $windowSeconds),
                $this->waitFor('ip_address = :ip AND email LIKE :prefix',
                    [':ip' => $this->ip(), ':prefix' => "$bucket:%"], $maxPerIp, $windowSeconds)
            );
        } catch (PDOException $e) {
            error_log("RateLimiter check error: " . $e->getMessage());
            return 0;
        }
    }

    public function record($bucket, $key) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO login_attempts (email, ip_address, is_successful) VALUES (:k, :ip, 0)"
            );
            $stmt->bindValue(':k', "$bucket:$key", PDO::PARAM_STR);
            $stmt->bindValue(':ip', $this->ip(), PDO::PARAM_STR);
            $stmt->execute();

            // Housekeeping: on about 2% of writes drop rows older than a day
            if (random_int(1, 50) === 1) {
                $this->conn->exec("DELETE FROM login_attempts WHERE attempt_time < NOW() - INTERVAL 1 DAY");
            }
        } catch (PDOException $e) {
            error_log("RateLimiter record error: " . $e->getMessage());
        }
    }

    public function clear($bucket, $key) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM login_attempts WHERE email = :k");
            $stmt->bindValue(':k', "$bucket:$key", PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("RateLimiter clear error: " . $e->getMessage());
        }
    }

    private function waitFor($where, $params, $max, $windowSeconds) {
        $window = (int)$windowSeconds;
        $stmt = $this->conn->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, attempt_time, NOW()) AS age
             FROM login_attempts
             WHERE $where AND attempt_time > NOW() - INTERVAL $window SECOND
             ORDER BY attempt_time ASC"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $ages  = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = count($ages);

        if ($count < $max) {
            return 0;
        }

        // The (count - max + 1)th oldest attempt has to age out before another is allowed
        return max(1, $window - (int)$ages[$count - $max]);
    }

    private function ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>

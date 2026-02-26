<?php
/**
 * OTP Manager
 * Handles OTP generation, validation, and cleanup
 */

class OTPManager {
    private $conn;
    private $table_name = "otp_verification";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Generate 6-digit OTP
     * @return string
     */
    public function generateOTP() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Save OTP to database
     * @param int|null $user_id
     * @param string $email
     * @param string $otp
     * @param string $purpose (registration, login, password_reset)
     * @return bool
     */
    public function saveOTP($user_id, $email, $otp, $purpose = 'registration') {
        try {
            // Delete any existing unused OTPs for this email and purpose
            $this->deleteOldOTPs($email, $purpose);

            // Use MySQL's NOW() for expiry so PHP/MySQL timezone differences don't matter
            $query = "INSERT INTO " . $this->table_name . "
                     (user_id, email, otp_code, purpose, expires_at)
                     VALUES (:user_id, :email, :otp_code, :purpose, NOW() + INTERVAL 10 MINUTE)";

            $stmt = $this->conn->prepare($query);

            // Bind values — use bindValue with explicit types so user_id is never silently NULL
            if ($user_id !== null) {
                $stmt->bindValue(":user_id", (int)$user_id, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":user_id", null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(":email",    $email,   PDO::PARAM_STR);
            $stmt->bindValue(":otp_code", $otp,     PDO::PARAM_STR);
            $stmt->bindValue(":purpose",  $purpose, PDO::PARAM_STR);

            if($stmt->execute()) {
                return true;
            }

            return false;
        } catch(PDOException $e) {
            error_log("Save OTP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify OTP
     * @param string $email
     * @param string $otp
     * @param string $purpose
     * @return array|bool
     */
    public function verifyOTP($email, $otp, $purpose) {
        try {
            $query = "SELECT * FROM " . $this->table_name . "
                     WHERE email = :email
                     AND otp_code = :otp_code
                     AND purpose = :purpose
                     AND is_used = FALSE
                     AND expires_at > NOW()
                     ORDER BY created_at DESC
                     LIMIT 1";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":otp_code", $otp);
            $stmt->bindParam(":purpose", $purpose);

            $stmt->execute();

            // Use fetch() directly — rowCount() is unreliable for SELECT in some PDO drivers
            $row = $stmt->fetch();
            if ($row !== false) {
                // Mark OTP as used
                $this->markOTPAsUsed($row['otp_id']);
                return $row;
            }

            return false;
        } catch(PDOException $e) {
            error_log("Verify OTP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark OTP as used
     * @param int $otp_id
     * @return bool
     */
    private function markOTPAsUsed($otp_id) {
        try {
            $query = "UPDATE " . $this->table_name . "
                     SET is_used = TRUE
                     WHERE otp_id = :otp_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":otp_id", $otp_id);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Mark OTP used error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete old OTPs for email and purpose
     * @param string $email
     * @param string $purpose
     * @return bool
     */
    private function deleteOldOTPs($email, $purpose) {
        try {
            $query = "DELETE FROM " . $this->table_name . "
                     WHERE email = :email
                     AND purpose = :purpose
                     AND is_used = FALSE";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":purpose", $purpose);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Delete old OTPs error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cleanup expired OTPs
     * @return bool
     */
    public function cleanupExpiredOTPs() {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE expires_at < NOW()";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Cleanup OTPs error: " . $e->getMessage());
            return false;
        }
    }
}
?>

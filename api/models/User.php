<?php
/**
 * User Model
 * Three-Tier Architecture - Data Access Layer
 * Handles all database operations for users
 */

class User {
    private $conn;
    private $table_name = "users";

    // User properties
    public $user_id;
    public $email;
    public $full_name;
    public $phone;
    public $user_type;
    public $is_verified;
    public $is_active;
    public $created_at;
    public $last_login;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Check if email exists
     * @param string $email
     * @return bool
     */
    public function emailExists($email) {
        $query = "SELECT user_id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if phone exists
     * @param string $phone
     * @return bool
     */
    public function phoneExists($phone) {
        $query = "SELECT user_id FROM " . $this->table_name . " WHERE phone = :phone LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Create new user
     * @return bool|int Returns user_id on success, false on failure
     */
    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . "
                     (email, full_name, phone, user_type, is_verified)
                     VALUES
                     (:email, :full_name, :phone, :user_type, :is_verified)";

            $stmt = $this->conn->prepare($query);

            // Sanitize inputs
            $this->email = htmlspecialchars(strip_tags($this->email));
            $this->full_name = htmlspecialchars(strip_tags($this->full_name));
            $this->phone = htmlspecialchars(strip_tags($this->phone));
            $this->user_type = htmlspecialchars(strip_tags($this->user_type));

            // Bind values
            $stmt->bindParam(":email", $this->email);
            $stmt->bindParam(":full_name", $this->full_name);
            $stmt->bindParam(":phone", $this->phone);
            $stmt->bindParam(":user_type", $this->user_type);
            $stmt->bindParam(":is_verified", $this->is_verified);

            if($stmt->execute()) {
                $this->user_id = $this->conn->lastInsertId();
                return $this->user_id;
            }

            return false;
        } catch(PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user by email
     * @param string $email
     * @return bool|array
     */
    public function getUserByEmail($email) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();

            if($stmt->rowCount() > 0) {
                return $stmt->fetch();
            }

            return false;
        } catch(PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user by ID
     * @param int $user_id
     * @return bool|array
     */
    public function getUserById($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            if($stmt->rowCount() > 0) {
                return $stmt->fetch();
            }

            return false;
        } catch(PDOException $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify user email
     * @param int $user_id
     * @return bool
     */
    public function verifyUser($user_id) {
        try {
            $query = "UPDATE " . $this->table_name . "
                     SET is_verified = TRUE
                     WHERE user_id = :user_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Verify user error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login time
     * @param int $user_id
     * @return bool
     */
    public function updateLastLogin($user_id) {
        try {
            $query = "UPDATE " . $this->table_name . "
                     SET last_login = NOW()
                     WHERE user_id = :user_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create customer profile
     * @param int $user_id
     * @return bool
     */
    public function createCustomerProfile($user_id) {
        try {
            $query = "INSERT INTO customer_profiles (user_id) VALUES (:user_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Create customer profile error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create shop profile
     * @param int $user_id
     * @param string $shop_name
     * @return bool
     */
    public function createShopProfile($user_id, $shop_name, $shop_image = null) {
        try {
            $shop_slug = $this->generateSlug($shop_name);

            $query = "INSERT INTO shop_profiles (user_id, shop_name, shop_slug, shop_image)
                     VALUES (:user_id, :shop_name, :shop_slug, :shop_image)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":shop_name", $shop_name);
            $stmt->bindParam(":shop_slug", $shop_slug);
            $stmt->bindParam(":shop_image", $shop_image);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Create shop profile error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate slug from name
     * @param string $name
     * @return string
     */
    private function generateSlug($name) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        return $slug . '-' . uniqid();
    }

    /**
     * Get user with profile data
     * @param int $user_id
     * @return bool|array
     */
    public function getUserWithProfile($user_id) {
        try {
            $user = $this->getUserById($user_id);

            if (!$user) {
                return false;
            }

            // Get profile based on user type
            if ($user['user_type'] === 'customer') {
                $query = "SELECT * FROM customer_profiles WHERE user_id = :user_id";
            } elseif ($user['user_type'] === 'shop') {
                $query = "SELECT * FROM shop_profiles WHERE user_id = :user_id";
            } elseif ($user['user_type'] === 'admin') {
                $query = "SELECT * FROM admin_profiles WHERE user_id = :user_id";
            } else {
                return $user;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            $profile = $stmt->fetch();

            return array_merge($user, $profile ? $profile : []);
        } catch(PDOException $e) {
            error_log("Get user with profile error: " . $e->getMessage());
            return false;
        }
    }
}
?>

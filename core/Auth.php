<?php
/**
 * Authentication Class
 * Crystal Chess Tournament Booking Platform
 * File: core/Auth.php
 */
require_once __DIR__ . '/../config/config.php';


class Auth
{
    private $db;

    public function __construct($pdo = null)
    {
        if ($pdo) {
            $this->db = $pdo;
        } else {
            $this->db = Database::getInstance()->getConnection();
        }
    }

    /**
     * Check if user is logged in
     */
    public static function check()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     */
    public static function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user type
     */
    public static function getUserType()
    {
        return $_SESSION['user_type'] ?? 'guest';
    }

    /**
     * Get current user data
     */
    public static function getUser()
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Get current user name
     */
    public static function getUserName()
    {
        return $_SESSION['user_name'] ?? 'Guest';
    }

    /**
     * Get current user email
     */
    public static function getUserEmail()
    {
        return $_SESSION['user_email'] ?? null;
    }

    /**
     * Login user
     */
    public function login($email, $password, $remember = false)
    {
        try {
            // Find user by email
            $stmt = $this->db->prepare("
                SELECT user_id, email, password_hash, full_name, user_type, user_status, email_verified 
                FROM users 
                WHERE email = ? 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.'
                ];
            }

            // Check if account is active
            if ($user['user_status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Your account has been suspended. Please contact support.'
                ];
            }

            // Check if email is verified
            if (!$user['email_verified']) {
                return [
                    'success' => false,
                    'message' => 'Please verify your email address before logging in.'
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.'
                ];
            }

            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Store full user data for easy access
            $_SESSION['user'] = [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'user_type' => $user['user_type'],
                'user_status' => $user['user_status']
            ];

            // Update last login
            $updateStmt = $this->db->prepare("
                UPDATE users 
                SET last_login = NOW() 
                WHERE user_id = ?
            ");
            $updateStmt->execute([$user['user_id']]);

            // Set remember me cookie if requested
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
            }

            return [
                'success' => true,
                'message' => 'Login successful!',
                'user_type' => $user['user_type']
            ];
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ];
        }
    }

    /**
     * Register new user
     */
    public function register($data)
    {
        try {
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);

            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Email address already registered.'
                ];
            }

            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, full_name, phone, user_type, user_status, email_verified) 
                VALUES (?, ?, ?, ?, ?, 'active', 0)
            ");

            $userType = $data['user_type'] ?? 'player';

            $stmt->execute([
                $data['email'],
                $passwordHash,
                $data['full_name'],
                $data['phone'] ?? null,
                $userType
            ]);

            $userId = $this->db->lastInsertId();

            // Auto-verify in development
            if (ENVIRONMENT === 'development') {
                $updateStmt = $this->db->prepare("UPDATE users SET email_verified = 1 WHERE user_id = ?");
                $updateStmt->execute([$userId]);
            }

            return [
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
                'user_id' => $userId
            ];
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during registration. Please try again.'
            ];
        }
    }

    /**
     * Logout user
     */
    public static function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear all session data
        $_SESSION = [];

        // Destroy session cookie if exists
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Delete remember token if exists
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 42000, '/');
        }

        // Destroy session
        session_destroy();
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($role)
    {
        if (is_array($role)) {
            return in_array(self::getUserType(), $role);
        }
        return self::getUserType() === $role;
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public static function require()
    {
        if (!self::check()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }

    /**
     * Alias for require()
     */
    public static function requireLogin()
    {
        self::require();
    }

    /**
     * Require specific role(s) (redirect if not authorized)
     * @param string|array $roles Single role or array of allowed roles
     */
    public static function requireRole($roles)
    {
        self::require();

        if (!self::hasRole($roles)) {
            // Redirect based on actual user type
            $userType = self::getUserType();
            
            switch ($userType) {
                case 'admin':
                    header('Location: ' . BASE_URL . '/admin-dashboard');
                    break;
                case 'organizer':
                    header('Location: ' . BASE_URL . '/organizer-dashboard');
                    break;
                case 'player':
                default:
                    header('Location: ' . BASE_URL . '/dashboard');
                    break;
            }
            exit;
        }
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin()
    {
        return self::getUserType() === 'admin';
    }

    /**
     * Check if user is organizer
     */
    public static function isOrganizer()
    {
        return self::getUserType() === 'organizer';
    }

    /**
     * Check if user is player
     */
    public static function isPlayer()
    {
        return self::getUserType() === 'player';
    }

    /**
     * Get user's full profile from database
     */
    public function getUserProfile($userId = null)
    {
        if (!$userId) {
            $userId = self::getUserId();
        }

        if (!$userId) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT user_id, email, full_name, phone, user_type, user_status, 
                       email_verified, created_at, last_login, profile_image
                FROM users 
                WHERE user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user profile error: " . $e->getMessage());
            return null;
        }
    }
}
<?php
/**
 * TRUSTLINK - Authentication Middleware
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Session validation and role-based access control for all protected API endpoints
 * Features:
 * - Session validation
 * - Role-based access control (RBAC)
 * - Ownership validation helpers
 * - CSRF protection
 * - Rate limiting integration
 * - Session timeout management
 * - User status checking (suspended/active)
 * 
 * Usage:
 *   require_once __DIR__ . '/../utils/auth_middleware.php';
 *   $auth = new AuthMiddleware();
 *   $user = $auth->requireAuth(); // Returns user data or exits with 401
 *   $auth->requireRole(UserRole::FARMER); // Checks role
 *   $auth->requireOwnership($table, $id, $userIdColumn); // Checks ownership
 */

namespace TrustLink\Utils {

    // 1. LOAD SECURE SESSION CONFIG FIRST
    require_once __DIR__ . '/../config/session_config.php';

    // 2. Load other dependencies
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/response.php';

    use TrustLink\Config\Database;
    use TrustLink\Config\UserRole;
    use TrustLink\Config\SessionKeys;
    use TrustLink\Config\ErrorMessages;
    use TrustLink\Config\ApiResponseCode;

    class AuthMiddleware
    {
        /**
         * @var array|null Current authenticated user data
         */
        private $currentUser = null;

        /**
         * @var int Session lifetime in seconds (default: 2 hours)
         */
        private $sessionLifetime = 7200;

        /**
         * @var bool Whether session was started by this class
         */
        private $sessionStarted = false;

        /**
         * Constructor - Initialize session and load user if authenticated
         */
        public function __construct()
        {
            $this->initSession();
            $this->sessionLifetime = (int)(getenv('SESSION_LIFETIME') ?: 7200);

            // Load current user if session exists
            if ($this->isAuthenticated()) {
                $this->loadCurrentUser();
            }
        }

        /**
         * Initialize session - Delegated to secure session_config.php
         * @return void
         */
        private function initSession()
        {
            // The session is already started and configured by session_config.php
            $this->sessionStarted = (session_status() === PHP_SESSION_ACTIVE);

            if ($this->sessionStarted) {
                // Regenerate session ID periodically to prevent fixation
                $this->regenerateSessionIfNeeded();

                // Check session timeout
                $this->checkSessionTimeout();
            }
        }

        /**
         * Regenerate session ID periodically
         * @return void
         */
        private function regenerateSessionIfNeeded()
        {
            if (!isset($_SESSION[SessionKeys::LAST_ACTIVITY])) {
                $_SESSION[SessionKeys::LAST_ACTIVITY] = time();
                return;
            }

            // Regenerate every 30 minutes
            if (time() - $_SESSION[SessionKeys::LAST_ACTIVITY] > 1800) {
                session_regenerate_id(true);
                $_SESSION[SessionKeys::LAST_ACTIVITY] = time();
            }
        }

        /**
         * Check and enforce session timeout
         * @return void
         */
        private function checkSessionTimeout()
        {
            if (isset($_SESSION[SessionKeys::LAST_ACTIVITY])) {
                $inactiveTime = time() - $_SESSION[SessionKeys::LAST_ACTIVITY];

                if ($inactiveTime > $this->sessionLifetime) {
                    $this->logout();
                    Response::unauthorized(ErrorMessages::AUTH_SESSION_EXPIRED);
                }
            }

            // Update last activity
            $_SESSION[SessionKeys::LAST_ACTIVITY] = time();
        }

        /**
         * Load current user data from database
         * @return void
         */
        private function loadCurrentUser()
        {
            if (!$this->isAuthenticated()) {
                return;
            }

            try {
                $userId = $_SESSION[SessionKeys::USER_ID];
                $db = Database::getInstance();
                $stmt = $db->prepare("
                    SELECT id, name, phone, email, role, admin_type, trust_score, verification_tier, 
                           county, subcounty, ward, profile_photo, status, created_at
                    FROM users 
                    WHERE id = ? AND status = true
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if ($user) {
                    $this->currentUser = $user;

                    // Sync session with latest data
                    $_SESSION[SessionKeys::USER_NAME] = $user['name'];
                    $_SESSION[SessionKeys::USER_ROLE] = $user['role'];
                    $_SESSION['admin_type'] = $user['admin_type'] ?? null;
                    $_SESSION[SessionKeys::USER_TRUST_SCORE] = $user['trust_score'];
                    $_SESSION[SessionKeys::USER_VERIFICATION_TIER] = $user['verification_tier'];
                } else {
                    // User no longer exists or is suspended
                    error_log("AuthMiddleware: User not found or not active for ID: " . $userId);
                    $this->logout();
                }
            } catch (\PDOException $e) {
                // Log error but don't expose to client
                error_log("Auth middleware error: " . $e->getMessage());
            }
        }

        /**
         * Check if user is authenticated
         * @return bool
         */
        public function isAuthenticated()
        {
            return isset($_SESSION[SessionKeys::USER_ID]) && isset($_SESSION[SessionKeys::USER_NAME]);
        }

        /**
         * Require authentication - returns user data or exits with 401
         * @return array Current user data
         */
        public function requireAuth()
        {
            if (!$this->isAuthenticated()) {
                Response::unauthorized(ErrorMessages::AUTH_UNAUTHORIZED);
            }

            if ($this->currentUser === null) {
                $this->loadCurrentUser();
            }

            // Check if user is still active
            if ($this->currentUser === null) {
                Response::unauthorized(ErrorMessages::AUTH_ACCOUNT_SUSPENDED);
            }

            return $this->currentUser;
        }

        /**
         * Require specific user role
         * @param string|array $roles Required role(s)
         * @return array Current user data
         */
        public function requireRole($roles)
        {
            $user = $this->requireAuth();

            $allowedRoles = is_array($roles) ? $roles : [$roles];

            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden(ErrorMessages::AUTH_UNAUTHORIZED);
            }

            return $user;
        }

        /**
         * Require farmer role
         * @return array Current user data
         */
        public function requireFarmer()
        {
            return $this->requireRole(UserRole::FARMER);
        }

        /**
         * Require buyer role
         * @return array Current user data
         */
        public function requireBuyer()
        {
            return $this->requireRole(UserRole::BUYER);
        }

        /**
         * Require service provider role
         * @return array Current user data
         */
        public function requireServiceProvider()
        {
            return $this->requireRole(UserRole::SERVICE_PROVIDER);
        }

        /**
         * Require admin role
         * @return array Current user data
         */
        public function requireAdmin()
        {
            return $this->requireRole(UserRole::ADMIN);
        }

        /**
         * Check if current user has specific role
         * @param string|array $roles Role(s) to check
         * @return bool
         */
        public function hasRole($roles)
        {
            if (!$this->isAuthenticated()) {
                return false;
            }

            $userRole = $_SESSION[SessionKeys::USER_ROLE] ?? null;

            if ($userRole === null) {
                return false;
            }

            $allowedRoles = is_array($roles) ? $roles : [$roles];

            return in_array($userRole, $allowedRoles);
        }

        /**
         * Check if current user is a farmer
         * @return bool
         */
        public function isFarmer()
        {
            return $this->hasRole(UserRole::FARMER);
        }

        /**
         * Check if current user is a buyer
         * @return bool
         */
        public function isBuyer()
        {
            return $this->hasRole(UserRole::BUYER);
        }

        /**
         * Check if current user is admin
         * @return bool
         */
        public function isAdmin()
        {
            return $this->hasRole(UserRole::ADMIN);
        }

        /**
         * Require ownership of a resource
         * @param string $table Table name
         * @param int $resourceId Resource ID
         * @param string $userIdColumn Column name that stores user ID (default: user_id)
         * @param string|null $customMessage Custom error message
         * @return bool True if owner, exits with 403 if not
         */
        public function requireOwnership($table, $resourceId, $userIdColumn = 'user_id', $customMessage = null)
        {
            $user = $this->requireAuth();

            try {
                $db = Database::getInstance();

                // Sanitize table and column names (prevent SQL injection)
                $allowedTables = ['products', 'orders', 'reviews', 'cart_items'];
                if (!in_array($table, $allowedTables, true)) {
                    Response::serverError('Invalid table for ownership check');
                }

                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM $table 
                    WHERE id = ? AND $userIdColumn = ?
                ");
                $stmt->execute([$resourceId, $user['id']]);
                $result = $stmt->fetch();

                if ($result['count'] == 0) {
                    $message = $customMessage ?? 'You do not have permission to access this resource';
                    Response::forbidden($message);
                }

                return true;
            } catch (\PDOException $e) {
                error_log("Ownership check error: " . $e->getMessage());
                Response::serverError('Failed to verify ownership');
            }

            return false;
        }

        /**
         * Require product ownership (farmer owns the product)
         * @param int $productId Product ID
         * @return bool
         */
        public function requireProductOwnership($productId)
        {
            $user = $this->requireFarmer();

            try {
                $db = Database::getInstance();
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM products 
                    WHERE id = ? AND farmer_id = ? AND status = true
                ");
                $stmt->execute([$productId, $user['id']]);
                $result = $stmt->fetch();

                if ($result['count'] == 0) {
                    Response::forbidden('You do not own this product');
                }

                return true;
            } catch (\PDOException $e) {
                error_log("Product ownership check error: " . $e->getMessage());
                Response::serverError('Failed to verify product ownership');
            }

            return false;
        }

        /**
         * Require order ownership (buyer or farmer of the order)
         * @param int $orderId Order ID
         * @param bool $allowFarmer Whether to allow farmer access (default: true)
         * @return array Order data
         */
        public function requireOrderAccess($orderId, $allowFarmer = true)
        {
            $user = $this->requireAuth();

            try {
                $db = Database::getInstance();
                $stmt = $db->prepare("
                    SELECT * FROM orders 
                    WHERE id = ?
                ");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();

                if (!$order) {
                    Response::notFound('Order');
                }

                $isBuyer = ($order['buyer_id'] == $user['id']);
                $isFarmer = ($allowFarmer && $order['farmer_id'] == $user['id']);
                $isAdmin = $this->isAdmin();

                if (!$isBuyer && !$isFarmer && !$isAdmin) {
                    Response::forbidden('You do not have access to this order');
                }

                return $order;
            } catch (\PDOException $e) {
                error_log("Order access check error: " . $e->getMessage());
                Response::serverError('Failed to verify order access');
            }

            return null;
        }

        /**
         * Get current authenticated user
         * @return array|null
         */
        public function getCurrentUser()
        {
            return $this->currentUser;
        }

        /**
         * Get current user ID
         * @return int|null
         */
        public function getUserId()
        {
            return $this->isAuthenticated() ? $_SESSION[SessionKeys::USER_ID] : null;
        }

        /**
         * Get current user role
         * @return string|null
         */
        public function getUserRole()
        {
            return $this->isAuthenticated() ? $_SESSION[SessionKeys::USER_ROLE] : null;
        }

        /**
         * Login user (create session)
         * @param array $user User data from database
         * @param bool $remember Remember me (extend session)
         * @return void
         */
        public function login($user, $remember = false)
        {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            // Set session data
            $_SESSION[SessionKeys::USER_ID] = $user['id'];
            $_SESSION[SessionKeys::USER_NAME] = $user['name'];
            $_SESSION[SessionKeys::USER_PHONE] = $user['phone'];
            $_SESSION[SessionKeys::USER_ROLE] = $user['role'];
            $_SESSION['admin_type'] = $user['admin_type'] ?? null;
            $_SESSION[SessionKeys::USER_TRUST_SCORE] = $user['trust_score'] ?? 0;
            $_SESSION[SessionKeys::USER_VERIFICATION_TIER] = $user['verification_tier'] ?? 'basic';
            $_SESSION[SessionKeys::LAST_ACTIVITY] = time();

            // Update last login in database
            try {
                $db = Database::getInstance();
                $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
            } catch (\PDOException $e) {
                error_log("Failed to update last login: " . $e->getMessage());
            }

            $this->currentUser = $user;
        }

        /**
         * Logout user (destroy session)
         * @return void
         */
        public function logout()
        {
            // Unset all session variables
            $_SESSION = [];

            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();

                // Sanitize domain
                $domain = $params["domain"] ?? '';
                if (!$domain || $domain === '""' || trim($domain) === '') {
                    $domain = '';
                }

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $domain,
                    $params["secure"],
                    $params["httponly"]
                );
            }

            // Destroy session
            session_destroy();

            $this->currentUser = null;
            $this->sessionStarted = false;
        }

        /**
         * Generate CSRF token
         * @return string
         */
        public function generateCsrfToken()
        {
            if (empty($_SESSION[SessionKeys::CSRF_TOKEN])) {
                $_SESSION[SessionKeys::CSRF_TOKEN] = bin2hex(random_bytes(32));
            }

            return $_SESSION[SessionKeys::CSRF_TOKEN];
        }

        /**
         * Verify CSRF token
         * @param string $token Token to verify
         * @return bool
         */
        public function verifyCsrfToken($token)
        {
            if (empty($_SESSION[SessionKeys::CSRF_TOKEN])) {
                return false;
            }

            return hash_equals($_SESSION[SessionKeys::CSRF_TOKEN], $token);
        }

        /**
         * Require valid CSRF token
         * @param string $token Token from request
         * @return void
         */
        public function requireCsrfToken($token)
        {
            if (!$this->verifyCsrfToken($token)) {
                Response::error('Invalid CSRF token', ApiResponseCode::FORBIDDEN);
            }
        }

        /**
         * Check rate limit for current user/IP
         * @param string $action Action type (login, api, etc.)
         * @param int $limit Max requests per window
         * @param int $window Time window in seconds
         * @return bool
         */
        public function checkRateLimit($action, $limit = 60, $window = 60)
        {
            $key = 'rate_limit_' . $action . '_' . ($this->getUserId() ?: $_SERVER['REMOTE_ADDR']);

            if (!isset($_SESSION[$key])) {
                $_SESSION[$key] = ['count' => 1, 'first_request' => time()];
                return true;
            }

            $data = $_SESSION[$key];
            $timeSinceFirst = time() - $data['first_request'];

            if ($timeSinceFirst > $window) {
                // Reset window
                $_SESSION[$key] = ['count' => 1, 'first_request' => time()];
                return true;
            }

            if ($data['count'] >= $limit) {
                return false;
            }

            $_SESSION[$key]['count']++;
            return true;
        }

        /**
         * Enforce rate limit
         * @param string $action Action type
         * @param int $limit Max requests
         * @param int $window Time window
         * @return void
         */
        public function requireRateLimit($action, $limit = 60, $window = 60)
        {
            if (!$this->checkRateLimit($action, $limit, $window)) {
                Response::rateLimitExceeded($window);
            }
        }
    }

} // END namespace TrustLink\Utils

// ============================================================================
// GLOBAL NAMESPACE - Convenience functions for easy access
// ============================================================================

namespace {

    if (!function_exists('auth')) {
        /**
         * Get AuthMiddleware instance
         * @return \TrustLink\Utils\AuthMiddleware
         */
        function auth()
        {
            return new \TrustLink\Utils\AuthMiddleware();
        }
    }

    if (!function_exists('require_auth')) {
        /**
         * Require authentication
         * @return array Current user data
         */
        function require_auth()
        {
            return auth()->requireAuth();
        }
    }

    if (!function_exists('require_role')) {
        /**
         * Require specific role
         * @param string|array $roles
         * @return array
         */
        function require_role($roles)
        {
            return auth()->requireRole($roles);
        }
    }

    if (!function_exists('require_farmer')) {
        /**
         * Require farmer role
         * @return array
         */
        function require_farmer()
        {
            return auth()->requireFarmer();
        }
    }

    if (!function_exists('require_buyer')) {
        /**
         * Require buyer role
         * @return array
         */
        function require_buyer()
        {
            return auth()->requireBuyer();
        }
    }

    if (!function_exists('require_admin')) {
        /**
         * Require admin role
         * @return array
         */
        function require_admin()
        {
            return auth()->requireAdmin();
        }
    }

    if (!function_exists('current_user')) {
        /**
         * Get current authenticated user
         * @return array|null
         */
        function current_user()
        {
            return auth()->getCurrentUser();
        }
    }

    if (!function_exists('user_id')) {
        /**
         * Get current user ID
         * @return int|null
         */
        function user_id()
        {
            return auth()->getUserId();
        }
    }

    if (!function_exists('csrf_token')) {
        /**
         * Generate CSRF token
         * @return string
         */
        function csrf_token()
        {
            return auth()->generateCsrfToken();
        }
    }

    if (!function_exists('verify_csrf')) {
        /**
         * Verify CSRF token
         * @param string $token
         * @return bool
         */
        function verify_csrf($token)
        {
            return auth()->verifyCsrfToken($token);
        }
    }

    if (!function_exists('require_ownership')) {
        /**
         * Require ownership of resource
         * @param string $table
         * @param int $resourceId
         * @param string $userIdColumn
         * @return bool
         */
        function require_ownership($table, $resourceId, $userIdColumn = 'user_id')
        {
            return auth()->requireOwnership($table, $resourceId, $userIdColumn);
        }
    }

    if (!function_exists('require_product_owner')) {
        /**
         * Require product ownership
         * @param int $productId
         * @return bool
         */
        function require_product_owner($productId)
        {
            return auth()->requireProductOwnership($productId);
        }
    }

} // END global namespace























 
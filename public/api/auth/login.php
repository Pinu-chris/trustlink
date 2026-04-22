<?php
// Ensure session is started before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_error_log', 1);
error_reporting(E_ALL);


ini_set('display_errors', 1);
ini_set('display_error_log', 1);
error_reporting(E_ALL);
/**
 * TRUSTLINK - User Login API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Handles user authentication
 * Features:
 * - Phone + password authentication
 * - Account status check (suspended)
 * - Session creation
 * - Failed attempt tracking
 * - Rate limiting
 * - Last login tracking
 * 
 * HTTP Method: POST
 * Endpoint: /api/auth/login.php
 * 
 * Request Body (JSON):
 * {
 *     "phone": "0712345678",
 *     "password": "securepassword"
 * }
 * 
 * Response:
 * - 200: Login successful with user data
 * - 400: Missing fields
 * - 401: Invalid credentials or suspended account
 * - 429: Too many failed attempts
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\UserRole;
use TrustLink\Config\ErrorMessages;
use TrustLink\Config\SuccessMessages;
use TrustLink\Config\ApiResponseCode;
use TrustLink\Config\RegexPatterns;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth for rate limiting
$auth = new AuthMiddleware();

// ============================================================================
// RATE LIMITING (Prevent brute force)
// ============================================================================

// Check rate limit for login (max 10 attempts per 15 minutes)
$auth->requireRateLimit('login', 10, 900);

// ============================================================================
// REQUEST VALIDATION
// ============================================================================

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::badRequest('Invalid JSON payload');
}

// Validate required fields
$requiredFields = ['phone', 'password'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    Response::validationError([
        'missing_fields' => $missingFields
    ], 'Phone and password are required');
}

// Extract variables
$phone = trim($input['phone']);
$password = $input['password'];

// ============================================================================
// FIELD VALIDATION
// ============================================================================

$errors = [];

// Phone validation
if (!preg_match(RegexPatterns::PHONE_KENYA, $phone)) {
    $errors['phone'] = 'Invalid Kenyan phone number format';
}

// Password validation (basic)
if (strlen($password) < 1) {
    $errors['password'] = 'Password is required';
}

if (!empty($errors)) {
    Response::validationError($errors, 'Validation failed');
}

// ============================================================================
// TRACK FAILED ATTEMPTS (for this IP/phone combination)
// ============================================================================

$failedKey = 'login_failed_' . $phone . '_' . $_SERVER['REMOTE_ADDR'];

// Check if too many failed attempts
if (isset($_SESSION[$failedKey]) && $_SESSION[$failedKey]['count'] >= 5) {
    $timeSinceLast = time() - $_SESSION[$failedKey]['last_attempt'];
    if ($timeSinceLast < 1800) { // 30 minutes lockout
        Response::rateLimitExceeded(1800 - $timeSinceLast);
    } else {
        // Reset after lockout period
        unset($_SESSION[$failedKey]);
    }
}

// ============================================================================
// AUTHENTICATION
// ============================================================================

try {
    $db = Database::getInstance();
    
    // Fetch user by phone
    $stmt = $db->prepare("
        SELECT id, name, phone, email, password, role, admin_type, trust_score, 
             verification_tier, status, profile_photo, created_at
        FROM users 
        WHERE phone = ?
    ");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    // ============================================================================
    // CHECK IF USER EXISTS
    // ============================================================================
    
    if (!$user) {
        // Record failed attempt
        if (!isset($_SESSION[$failedKey])) {
            $_SESSION[$failedKey] = ['count' => 1, 'last_attempt' => time()];
        } else {
            $_SESSION[$failedKey]['count']++;
            $_SESSION[$failedKey]['last_attempt'] = time();
        }
        
        Response::unauthorized(ErrorMessages::AUTH_INVALID_CREDENTIALS);
    }
    
    // ============================================================================
    // CHECK ACCOUNT STATUS
    // ============================================================================
    
    if ($user['status'] == false) {
        Response::unauthorized(ErrorMessages::AUTH_ACCOUNT_SUSPENDED);
    }
    
    // ============================================================================
    // VERIFY PASSWORD
    // ============================================================================
    
    if (!password_verify($password, $user['password'])) {
        // Record failed attempt
        if (!isset($_SESSION[$failedKey])) {
            $_SESSION[$failedKey] = ['count' => 1, 'last_attempt' => time()];
        } else {
            $_SESSION[$failedKey]['count']++;
            $_SESSION[$failedKey]['last_attempt'] = time();
        }
        
        Response::unauthorized(ErrorMessages::AUTH_INVALID_CREDENTIALS);
    }
    
    // ============================================================================
    // CHECK IF PASSWORD NEEDS REHASH (upgrade to stronger hash)
    // ============================================================================
    
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $user['id']]);
    }
    
    // ============================================================================
    // CLEAR FAILED ATTEMPTS ON SUCCESS
    // ============================================================================
    
    unset($_SESSION[$failedKey]);
    
    // ============================================================================
    // CREATE SESSION (Login the user)
    // ============================================================================
    
    $auth->login($user);
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $user['id'],
            'action' => 'user_login',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode(['role' => $user['role']])
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        // Log but don't fail login
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // ============================================================================
    // PREPARE USER DATA FOR RESPONSE (exclude sensitive fields)
    // ============================================================================
    
    $userData = [
        'id' => $user['id'],
        'name' => $user['name'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'admin_type' => $user['admin_type'],
        'trust_score' => (float) $user['trust_score'],
        'verification_tier' => $user['verification_tier'],
        'profile_photo' => $user['profile_photo'],
        'joined_at' => $user['created_at']
    ];
    
    // Add role-specific dashboard redirect URL
    $dashboardUrls = [
        UserRole::BUYER => '/dashboard.html',
        UserRole::FARMER => '/dashboard.html',
        UserRole::SERVICE_PROVIDER => '/dashboard.html',
        UserRole::ADMIN => '/admin.html'
    ];
    
    $redirectUrl = $dashboardUrls[$user['role']] ?? '/dashboard.html';
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
        error_log("Login successful for user ID: " . $user['id']);
        error_log("Session ID: " . session_id());
        error_log("Session data: " . print_r($_SESSION, true));

// In the success response section
        Response::success([
            'user' => $userData,
            'redirect' => 'dashboard.php' // Change from dashboard.html to dashboard.php
        ], 'Login successful');
    
} catch (\PDOException $e) {
    // Log the error
    error_log("Login error: " . $e->getMessage());
    
    // Return user-friendly error
    Response::serverError(ErrorMessages::SERVER_ERROR, false, $e);
}
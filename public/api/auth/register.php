<?php
// Add these 2 lines right at the top
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * TRUSTLINK - User Registration API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Handles new user registration
 * Features:
 * - Phone number validation (Kenyan format)
 * - Password strength validation
 * - Duplicate phone check
 * - Role assignment (buyer/farmer/service_provider)
 * - Session creation on successful registration
 * - CSRF protection
 * - Rate limiting
 * 
 * HTTP Method: POST
 * Endpoint: /api/auth/register.php
 * 
 * Request Body (JSON):
 * {
 *     "name": "John Doe",
 *     "phone": "0712345678",
 *     "password": "securepassword",
 *     "password_confirm": "securepassword",
 *     "role": "buyer",           // buyer, farmer, service_provider
 *     "location": "Kilimani"      // Optional
 * }
 * 
 * Response:
 * - 201: User created successfully
 * - 400: Validation errors
 * - 409: Phone already exists
 * - 429: Rate limit exceeded
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
use TrustLink\Config\VerificationTier;
use TrustLink\Config\ErrorMessages;
use TrustLink\Config\SuccessMessages;
use TrustLink\Config\ApiResponseCode;
use TrustLink\Config\RegexPatterns;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth for rate limiting
$auth = new AuthMiddleware();

// ============================================================================
// RATE LIMITING (Prevent abuse)
// ============================================================================

// Check rate limit for registration (max 5 attempts per hour)
$auth->requireRateLimit('register', 5, 3600);

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
$requiredFields = ['name', 'phone', 'password', 'password_confirm', 'role'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    Response::validationError([
        'missing_fields' => $missingFields,
        'message' => 'Required fields: ' . implode(', ', $missingFields)
    ], 'Missing required fields');
}

// Extract variables
$name = trim($input['name']);
$phone = trim($input['phone']);
$password = $input['password'];
$passwordConfirm = $input['password_confirm'];
$role = strtolower(trim($input['role']));
$location = isset($input['location']) ? trim($input['location']) : null;

// ============================================================================
// FIELD VALIDATION
// ============================================================================

$errors = [];

// 1. Name validation
if (strlen($name) < 2) {
    $errors['name'] = 'Name must be at least 2 characters';
} elseif (strlen($name) > 100) {
    $errors['name'] = 'Name cannot exceed 100 characters';
} elseif (!preg_match(RegexPatterns::NAME, $name)) {
    $errors['name'] = 'Name can only contain letters, spaces, and hyphens';
}

// 2. Phone validation (Kenyan format)
if (!preg_match(RegexPatterns::PHONE_KENYA, $phone)) {
    $errors['phone'] = 'Invalid Kenyan phone number. Format: 07XXXXXXXX or 01XXXXXXXX';
}

// 3. Password validation
if (strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters';
}
if ($password !== $passwordConfirm) {
    $errors['password_confirm'] = 'Passwords do not match';
}

// 4. Role validation
$allowedRoles = [UserRole::BUYER, UserRole::FARMER, UserRole::SERVICE_PROVIDER];
if (!in_array($role, $allowedRoles)) {
    $errors['role'] = 'Invalid role. Allowed: buyer, farmer, service_provider';
}

// 5. Location validation (optional)
if ($location !== null && strlen($location) > 100) {
    $errors['location'] = 'Location cannot exceed 100 characters';
}

// Return validation errors if any
if (!empty($errors)) {
    Response::validationError($errors, 'Validation failed');
}

// ============================================================================
// DATABASE VALIDATION - CHECK DUPLICATE PHONE
// ============================================================================

try {
    $db = Database::getInstance();
    
    // Check if phone already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        Response::conflict(ErrorMessages::REG_PHONE_EXISTS);
    }
    
    // ============================================================================
    // CREATE USER
    // ============================================================================
    
    // Hash password (bcrypt with cost from env)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, [
        'cost' => (int) (getenv('PASSWORD_HASH_COST') ?: 12)
    ]);
    
// Prepare user data
    $userData = [
        'name' => $name,
        'phone' => $phone,
        'password' => $passwordHash,
        'role' => $role, // Must be lowercase 'buyer', 'farmer', or 'service_provider'
        'verification_tier' => 'basic', // Use the string directly to match your ENUM
        'location' => $location, // This now works because of the ALTER TABLE above
        'status' => true,
        'id_verified' => false,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Insert user
    // 1. Prepare the SQL string with placeholders
$sql = "INSERT INTO users (name, phone, password, role, verification_tier, location, status, id_verified, created_at, updated_at) 
        VALUES (:name, :phone, :password, :role, :tier, :location, :status, :id_v, :created, :updated) 
        RETURNING id";

$stmt = $db->prepare($sql);

// 2. Execute and bind the data
$stmt->execute([
    ':name'     => $userData['name'],
    ':phone'    => $userData['phone'],
    ':password' => $userData['password'],
    ':role'     => $userData['role'],
    ':tier'     => $userData['verification_tier'],
    ':location' => $userData['location'],
    ':status'   => $userData['status'] ? 'true' : 'false', // PgSQL likes strings or booleans
    ':id_v'     => $userData['id_verified'] ? 'true' : 'false',
    ':created'  => $userData['created_at'],
    ':updated'  => $userData['updated_at']
]);

// 3. Get the ID generated by PostgreSQL
$userId = $stmt->fetchColumn();
    
    // ============================================================================
    // FETCH CREATED USER DATA
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT id, name, phone, role, trust_score, verification_tier, location, created_at
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // ============================================================================
    // LOG THE ACTIVITY (for analytics)
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $userId,
            'action' => 'user_registered',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode(['role' => $role])
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        // Log but don't fail registration
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // ============================================================================
    // AUTO-LOGIN AFTER REGISTRATION (Optional - can be disabled)
    // ============================================================================
    
    // Auto-login the user (create session)
    $auth->login($user);
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::created([
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'trust_score' => (float) $user['trust_score'],
            'verification_tier' => $user['verification_tier'],
            'location' => $user['location'],
            'joined_at' => $user['created_at']
        ]
    ], SuccessMessages::REGISTER_SUCCESS);
    
} catch (\Exception $e) { // Changed from \PDOException to \Exception to catch everything
    // Log the error
    error_log("Registration error: " . $e->getMessage());
    
    // CHANGE false TO true HERE
    Response::serverError(ErrorMessages::SERVER_ERROR, true, $e);
}
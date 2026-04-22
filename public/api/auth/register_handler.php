<?php
/**
 * TRUSTLINK - User Registration Handler
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Handles new user registration via form POST
 * Features:
 * - Input validation
 * - Duplicate phone check
 * - Password hashing
 * - Account creation
 * - Success/error redirects
 * 
 * HTTP Method: POST
 * Endpoint: /api/auth/register_handler.php
 * 
 * Form Fields:
 * - name: Full name
 * - phone: Kenyan phone number
 * - role: buyer/farmer/service_provider
 * - location: User location (optional)
 * - password: User password
 * - password_confirm: Password confirmation
 */

// Start session
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\UserRole;

// Get POST data
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'buyer';
$location = trim($_POST['location'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

// ============================================================================
// INPUT VALIDATION
// ============================================================================

$errors = [];

// Name validation
if (empty($name)) {
    $errors[] = 'Full name is required';
} elseif (strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters';
} elseif (strlen($name) > 100) {
    $errors[] = 'Name cannot exceed 100 characters';
}

// Phone validation
if (empty($phone)) {
    $errors[] = 'Phone number is required';
} elseif (!preg_match('/^(07|01)[0-9]{8}$/', $phone)) {
    $errors[] = 'Invalid Kenyan phone number format (e.g., 0712345678)';
}

// Phone validation block ends here...

// Email validation
if (empty($email)) {
    $errors[] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

// Role validation
$allowedRoles = [UserRole::BUYER, UserRole::FARMER, UserRole::SERVICE_PROVIDER];
if (!in_array($role, $allowedRoles)) {
    $errors[] = 'Invalid role selected';
}

// Password validation
if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters';
} elseif (strlen($password) > 255) {
    $errors[] = 'Password cannot exceed 255 characters';
}

// Password confirmation
if ($password !== $passwordConfirm) {
    $errors[] = 'Passwords do not match';
}

// Location validation (optional)
if (!empty($location) && strlen($location) > 255) {
    $errors[] = 'Location cannot exceed 255 characters';
}

// If validation errors exist, redirect back with error message
if (!empty($errors)) {
    $errorMessage = implode(', ', $errors);
    header('Location: ../../public/register.php?error=' . urlencode($errorMessage));
    exit;
}

// ============================================================================
// DATABASE OPERATIONS
// ============================================================================

try {
    $db = Database::getInstance();
    
    // Check if phone number already exists
            // Check if phone OR email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? OR email = ?");
            $stmt->execute([$phone, $email]); 
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                header('Location: ../../public/register.php?error=' . urlencode('Phone number or email already registered.'));
                exit;
            }
    
    // Hash password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $db->prepare("
        INSERT INTO users (
            name, 
            phone, 
            email,
            password, 
            role, 
            location, 
            trust_score, 
            verification_tier, 
            status, 
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'basic', true, NOW(), NOW()) -- 2. Add one '?'
    ");
    
    // Set default trust score based on role
    $defaultTrustScore = 3.0;
    
    $result = $stmt->execute([
        $name,
        $phone,
        $email,
        $hashedPassword,
        $role,
        $location,
        $defaultTrustScore
    ]);
    
    if ($result) {
        // Get the newly created user ID
        $userId = $db->lastInsertId();
        
        // Log registration activity
        try {
            $activityStmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, ip_address, user_agent, details, created_at)
                VALUES (?, 'user_registered', ?, ?, ?, NOW())
            ");
            $activityStmt->execute([
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                json_encode(['role' => $role])
            ]);
        } catch (Exception $e) {
            // Log but don't fail registration
            error_log("Failed to log registration activity: " . $e->getMessage());
        }
        
        // Success - redirect to login page with success message
        $successMessage = 'Registration successful! Please login to continue.';
        header('Location: ../../public/login.php?success=' . urlencode($successMessage));
        exit;
    } else {
        // Database insert failed
        error_log("Registration failed - insert returned false for phone: $phone");
        header('Location: ../../public/register.php?error=' . urlencode('Registration failed. Please try again.'));
        exit;
    }
    
} catch (PDOException $e) {
    // Database error
    error_log("Registration database error: " . $e->getMessage());
    header('Location: ../../public/register.php?error=' . urlencode('Registration failed due to a system error. Please try again later.'));
    exit;
} catch (Exception $e) {
    // General error
    error_log("Registration general error: " . $e->getMessage());
    header('Location: ../../public/register.php?error=' . urlencode('Registration failed. Please try again.'));
    exit;
}
?>
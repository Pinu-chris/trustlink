<?php
 

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\SessionKeys;
use TrustLink\Config\UserRole;
use TrustLink\Utils\AuthMiddleware;

// Get POST data
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($phone) || empty($password)) {
    header('Location: ../../public/login.php?error=' . urlencode('Phone and password are required'));
    exit;
}

// Validate phone format
if (!preg_match('/^(07|01)[0-9]{8}$/', $phone)) {
    header('Location: ../../public/login.php?error=' . urlencode('Invalid Kenyan phone number format'));
    exit;
}

try {
    $db = Database::getInstance();
    
    // Fetch user
        $stmt = $db->prepare("
           SELECT id, name, phone, email, password, role, trust_score, 
                verification_tier, status, profile_photo, created_at, admin_type,
                must_change_password
            FROM users 
            WHERE phone = ?
        ");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        header('Location: ../../public/login.php?error=' . urlencode('Invalid phone number or password'));
        exit;
    }
    
    // Check account status
    if ($user['status'] == false) {
        header('Location: ../../public/login.php?error=' . urlencode('Your account has been suspended. Please contact support.'));
        exit;
    }
    
    // Verify password
        // Verify password
        if (!password_verify($password, $user['password'])) {
            header('Location: ../../public/login.php?error=' . urlencode('Invalid phone number or password'));
            exit;
        }

        // 🔥 FORCE PASSWORD CHANGE CHECK (CORRECT POSITION)
        if (!empty($user['must_change_password'])) {
            $_SESSION['force_password_change'] = true;
            $_SESSION['temp_user_id'] = $user['id']; // VERY IMPORTANT
            header('Location: ../../public/change-password.php');
            exit;
        }
    
    // Set session using AuthMiddleware
    $auth = new AuthMiddleware();
    $auth->login($user);
    
 
     
    
    // Debug logging
    error_log("=== LOGIN SUCCESS ===");
    error_log("User ID: {$user['id']}");
    error_log("User Name: {$user['name']}");
    error_log("User Role: {$user['role']}");
    error_log("Session ID: " . session_id());
    error_log("Session data: " . print_r($_SESSION, true));
    
    // Redirect based on role
    if ($user['role'] === 'admin') {
        header('Location: ../../public/admin.php');
    } else {
        header('Location: ../../public/dashboard.php');
    }
    exit;
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    header('Location: ../../public/login.php?error=' . urlencode('Login failed. Please try again.'));
    exit;
}
?>
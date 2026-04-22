<?php
/**
 * TRUSTLINK - User Logout Handler
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Handles user logout by destroying session and clearing cookies
 * Features:
 * - Session destruction
 * - Cookie cleanup
 * - Activity logging
 * - Redirect to login
 * 
 * HTTP Method: GET or POST
 * Endpoint: /api/auth/logout_handler.php
 */

// Start session to access session data
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load required files
require_once __DIR__ . '/../../config/db.php';

use TrustLink\Config\Database;

// Store user info for logging before destroying session
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'Unknown';
$userRole = $_SESSION['user_role'] ?? 'Unknown';

// Log logout activity if user was logged in
if ($userId) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, ip_address, user_agent, details, created_at)
            VALUES (?, 'user_logout', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode(['role' => $userRole])
        ]);
    } catch (Exception $e) {
        // Log but don't fail logout
        error_log("Failed to log logout activity: " . $e->getMessage());
    }
}

// ============================================================================
// CLEAR ALL SESSION DATA
// ============================================================================

// Unset all session variables
$_SESSION = [];

// ============================================================================
// DELETE SESSION COOKIE
// ============================================================================

// Check if session cookie is being used
if (ini_get("session.use_cookies")) {
    // Get session cookie parameters
    $params = session_get_cookie_params();
    
    // Delete the session cookie by setting expiration to past
    setcookie(
        session_name(),           // Cookie name
        '',                       // Empty value
        time() - 42000,          // Expire in the past
        $params["path"],         // Cookie path
        $params["domain"],       // Cookie domain
        $params["secure"],       // Secure flag
        $params["httponly"]      // HttpOnly flag
    );
}

// ============================================================================
// DESTROY THE SESSION
// ============================================================================

// Destroy the session completely
session_destroy();

// ============================================================================
// CLEAR ADDITIONAL COOKIES (Optional)
// ============================================================================

// Clear any remember-me cookies if they exist
if (isset($_COOKIE['trustlink_remember'])) {
    setcookie('trustlink_remember', '', time() - 42000, '/');
}

// Clear CSRF token cookie if it exists
if (isset($_COOKIE['trustlink_csrf'])) {
    setcookie('trustlink_csrf', '', time() - 42000, '/');
}

// ============================================================================
// DEBUG LOGGING (Optional - remove in production)
// ============================================================================

error_log("User logged out - ID: $userId, Name: $userName, Role: $userRole, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// ============================================================================
// REDIRECT TO LOGIN PAGE
// ============================================================================

// Check if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // For AJAX requests, return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
        'redirect' => '/trustfiles/public/login.php'
    ]);
    exit;
} else {
    // For regular requests, redirect to login page
    header('Location: ../../public/login.php');
    exit;
}
?>
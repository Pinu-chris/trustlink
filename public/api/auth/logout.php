<?php
/**
 * TRUSTLINK - User Logout API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Destroys user session
 * Features:
 * - Session destruction
 * - Activity logging
 * - CSRF protection (optional)
 * 
 * HTTP Method: POST
 * Endpoint: /api/auth/logout.php
 * 
 * Response:
 * - 200: Logout successful
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;
 
use TrustLink\Config\SuccessMessages;
 
 
 
 

// Initialize auth
$auth = new AuthMiddleware();

// Get user ID before logout (for logging)
$userId = $auth->getUserId();

// ============================================================================
// LOG THE ACTIVITY (before logout)
// ============================================================================

if ($userId) {
    try {
        $activityData = [
            'user_id' => $userId,
            'action' => 'user_logout',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        error_log("Failed to log logout: " . $e->getMessage());
    }
}

// ============================================================================
// DESTROY SESSION
// ============================================================================

$auth->logout();

// ============================================================================
// SUCCESS RESPONSE
// ============================================================================

Response::success(null, SuccessMessages::LOGOUT_SUCCESS);
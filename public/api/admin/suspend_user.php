<?php
require_once dirname(__DIR__, 2) . '/utils/auth_middleware.php';

// 2. ACTIVATE THE SHIELD (Crucial Step!)
// This one line ensures only Admins can proceed. 
// Everyone else (including Farmers/Buyers) gets kicked out immediately.
$adminData = require_admin(); // ✅ This looks in the Global space where we put it
/**
 * TRUSTLINK - Suspend User API (Admin Only)
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Suspends or reactivates user accounts
 * Features:
 * - Suspend active users
 * - Reactivate suspended users
 * - Cannot suspend admin users
 * - Cannot suspend self
 * - Activity logging
 * 
 * HTTP Method: POST
 * Endpoint: /api/admin/suspend_user.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "user_id": 123,
 *     "action": "suspend",      // suspend, reactivate
 *     "reason": "Violation of terms"  // Optional
 * }
 * 
 * Response:
 * - 200: User suspended/reactivated
 * - 400: Invalid action
 * - 401: Unauthorized
 * - 403: Cannot suspend admin or self
 * - 404: User not found
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\UserRole;
use TrustLink\Config\NotificationType;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require admin role
$auth = new AuthMiddleware();
$currentUser = $auth->requireAdmin();

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::badRequest('Invalid JSON payload');
}

// Validate required fields
$userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$action = isset($input['action']) ? $input['action'] : null;
$reason = isset($input['reason']) ? trim($input['reason']) : null;

if ($userId <= 0) {
    Response::badRequest('User ID is required');
}

$allowedActions = ['suspend', 'reactivate'];
if (!in_array($action, $allowedActions)) {
    Response::badRequest('Invalid action. Allowed: suspend, reactivate');
}

if (strlen($reason) > 500) {
    Response::badRequest('Reason cannot exceed 500 characters');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // FETCH TARGET USER
    // ============================================================================
    
    $stmt = $db->prepare("SELECT id, name, role, status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        Response::notFound('User');
    }
    
    // ============================================================================
    // PREVENT SELF-SUSPENSION
    // ============================================================================
    
    if ($targetUser['id'] == $currentUser['id']) {
        Response::forbidden('You cannot suspend your own account');
    }
    
    // ============================================================================
    // PREVENT SUSPENDING ADMIN USERS
    // ============================================================================
    
    if ($targetUser['role'] === UserRole::ADMIN) {
        Response::forbidden('Cannot suspend admin users');
    }

            // Prevent suspending founder
        if ($targetUser['role'] === UserRole::ADMIN && $targetUser['admin_type'] === 'founder') {
            Response::forbidden('Cannot suspend the founder account');
        }
    
    // ============================================================================
    // CHECK CURRENT STATUS VS ACTION
    // ============================================================================
    
    $isCurrentlyActive = (bool) $targetUser['status'];
    
    if ($action === 'suspend' && !$isCurrentlyActive) {
        Response::conflict('User is already suspended');
    }
    
    if ($action === 'reactivate' && $isCurrentlyActive) {
        Response::conflict('User is already active');
    }
    
    // ============================================================================
    // UPDATE USER STATUS
    // ============================================================================
    
    $newStatus = ($action === 'suspend') ? false : true;
    
    $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $userId]);
    
    // ============================================================================
    // CREATE NOTIFICATION FOR USER
    // ============================================================================
    
    if ($action === 'suspend') {
        $notificationTitle = 'Account Suspended';
        $notificationMessage = "Your account has been suspended. " . ($reason ? "Reason: {$reason}" : "Please contact support for more information.");
    } else {
        $notificationTitle = 'Account Reactivated';
        $notificationMessage = "Your account has been reactivated. You can now log in and use the platform again.";
    }
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, ?, ?, 'admin_action', NOW())
    ");
    $stmt->execute([$userId, $notificationTitle, $notificationMessage]);
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $currentUser['id'],
            'action' => 'user_' . $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode([
                'target_user_id' => $userId,
                'target_user_name' => $targetUser['name'],
                'reason' => $reason
            ])
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    $message = $action === 'suspend' 
        ? "User '{$targetUser['name']}' has been suspended successfully" 
        : "User '{$targetUser['name']}' has been reactivated successfully";
    
    Response::success([
        'user_id' => $userId,
        'user_name' => $targetUser['name'],
        'action' => $action,
        'new_status' => $action === 'suspend' ? 'suspended' : 'active',
        'reason' => $reason
    ], $message);
    
} catch (\PDOException $e) {
    error_log("Suspend user error: " . $e->getMessage());
    Response::serverError('Failed to update user status', false, $e);
}
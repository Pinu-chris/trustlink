<?php
require_once dirname(__DIR__, 2) . '/utils/auth_middleware.php';

// 2. ACTIVATE THE SHIELD (Crucial Step!)
// This one line ensures only Admins can proceed. 
// Everyone else (including Farmers/Buyers) gets kicked out immediately.
$adminData = require_admin(); // ✅ This looks in the Global space where we put it
/**
 * TRUSTLINK - Verify User API (Admin Only)
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Manually verify user identity and upgrade verification tiers
 * Features:
 * - ID verification approval
 * - Upgrade verification tier (basic → trusted → premium)
 * - Cannot verify self
 * - Activity logging
 * 
 * HTTP Method: POST
 * Endpoint: /api/admin/verify_user.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "user_id": 123,
 *     "action": "verify_id",        // verify_id, upgrade_tier
 *     "tier": "trusted"              // For upgrade_tier action
 * }
 * 
 * Response:
 * - 200: User verified/upgraded
 * - 400: Invalid action
 * - 401: Unauthorized
 * - 403: Cannot verify self
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
use TrustLink\Config\VerificationTier;
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

if ($userId <= 0) {
    Response::badRequest('User ID is required');
}

$allowedActions = ['verify_id', 'upgrade_tier'];
if (!in_array($action, $allowedActions)) {
    Response::badRequest('Invalid action. Allowed: verify_id, upgrade_tier');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // FETCH TARGET USER
    // ============================================================================
    
    $stmt = $db->prepare("SELECT id, name, role, id_verified, verification_tier FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        Response::notFound('User');
    }
    
    // ============================================================================
    // PREVENT SELF-VERIFICATION
    // ============================================================================
    
    if ($targetUser['id'] == $currentUser['id']) {
        Response::forbidden('You cannot verify your own account');
    }
    
    // ============================================================================
    // HANDLE VERIFY ID ACTION
    // ============================================================================
    
    if ($action === 'verify_id') {
        if ($targetUser['id_verified']) {
            Response::conflict('User is already ID verified');
        }
        
        $stmt = $db->prepare("UPDATE users SET id_verified = true, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        
        $notificationTitle = 'ID Verification Approved';
        $notificationMessage = "Your ID verification has been approved. Your account is now verified.";
        
        $successMessage = "User '{$targetUser['name']}' has been ID verified";
        $logAction = 'user_id_verified';
        
    } elseif ($action === 'upgrade_tier') {
        // ============================================================================
        // HANDLE UPGRADE TIER ACTION
        // ============================================================================
        
        $newTier = isset($input['tier']) ? $input['tier'] : null;
        $allowedTiers = [VerificationTier::TRUSTED, VerificationTier::PREMIUM];
        
        if (!$newTier || !in_array($newTier, $allowedTiers)) {
            Response::badRequest('Valid tier is required for upgrade. Allowed: trusted, premium');
        }
        
        // Validate tier progression
        $currentTier = $targetUser['verification_tier'];
        $tierOrder = [
            VerificationTier::BASIC => 1,
            VerificationTier::TRUSTED => 2,
            VerificationTier::PREMIUM => 3
        ];
        
        if ($tierOrder[$newTier] <= $tierOrder[$currentTier]) {
            Response::badRequest("Cannot downgrade from {$currentTier} to {$newTier}. Use upgrade only.");
        }
        
        // For premium tier, ensure ID is verified first
        if ($newTier === VerificationTier::PREMIUM && !$targetUser['id_verified']) {
            Response::badRequest('User must be ID verified before upgrading to premium tier');
        }
        
        $stmt = $db->prepare("UPDATE users SET verification_tier = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newTier, $userId]);
        
        $notificationTitle = 'Verification Tier Upgraded';
        $notificationMessage = "Your verification tier has been upgraded to " . VerificationTier::displayName($newTier);
        
        $successMessage = "User '{$targetUser['name']}' upgraded to " . VerificationTier::displayName($newTier);
        $logAction = 'user_tier_upgraded';
    }
    
    // ============================================================================
    // CREATE NOTIFICATION FOR USER
    // ============================================================================
    
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
            'action' => $logAction,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode([
                'target_user_id' => $userId,
                'target_user_name' => $targetUser['name'],
                'action_type' => $action,
                'new_tier' => $newTier ?? null
            ])
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // ============================================================================
    // FETCH UPDATED USER DATA
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT id, name, id_verified, verification_tier 
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch();
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success([
        'user_id' => $userId,
        'user_name' => $updatedUser['name'],
        'action' => $action,
        'id_verified' => (bool) $updatedUser['id_verified'],
        'verification_tier' => $updatedUser['verification_tier'],
        'verification_display' => VerificationTier::displayName($updatedUser['verification_tier'])
    ], $successMessage);
    
} catch (\PDOException $e) {
    error_log("Verify user error: " . $e->getMessage());
    Response::serverError('Failed to update user verification', false, $e);
}
<?php
/**
 * TRUSTLINK - Clear Cart API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Removes all items from user's shopping cart
 * Features:
 * - Bulk removal of all cart items
 * - Ownership validation
 * 
 * HTTP Method: POST
 * Endpoint: /api/cart/clear_cart.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Response:
 * - 200: Cart cleared successfully
 * - 401: Unauthorized
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\SuccessMessages;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// Only buyers can clear cart
$auth->requireBuyer();

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // GET COUNT BEFORE CLEAR (for logging)
    // ============================================================================
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $count = $stmt->fetch()['count'];
    
    // ============================================================================
    // CLEAR ALL CART ITEMS
    // ============================================================================
    
    $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    if ($count > 0) {
        try {
            $activityData = [
                'user_id' => $user['id'],
                'action' => 'cart_cleared',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => json_encode(['items_removed' => $count])
            ];
            Database::insert('activity_logs', $activityData);
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success([
        'items_removed' => $count
    ], SuccessMessages::CART_CLEARED);
    
} catch (\PDOException $e) {
    error_log("Clear cart error: " . $e->getMessage());
    Response::serverError('Failed to clear cart', false, $e);
}
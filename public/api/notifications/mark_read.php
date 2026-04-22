<?php
/**
 * TRUSTLINK - Mark Notifications Read API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Marks notifications as read
 * Features:
 * - Single notification mark read
 * - Mark all notifications as read
 * - Ownership validation
 * 
 * HTTP Method: POST
 * Endpoint: /api/notifications/mark_read.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "notification_id": 123,    // Optional - if not provided, marks all as read
 *     "mark_all": false           // Optional - set true to mark all
 * }
 * 
 * Response:
 * - 200: Notifications marked as read
 * - 400: Invalid request
 * - 401: Unauthorized
 * - 404: Notification not found
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::badRequest('Invalid JSON payload');
}

// Determine if marking single or all
$markAll = isset($input['mark_all']) ? (bool) $input['mark_all'] : false;
$notificationId = isset($input['notification_id']) ? (int) $input['notification_id'] : 0;

if (!$markAll && $notificationId <= 0) {
    Response::badRequest('Either notification_id or mark_all=true is required');
}

try {
    $db = Database::getInstance();
    $updatedCount = 0;
    
    if ($markAll) {
        // ============================================================================
        // MARK ALL NOTIFICATIONS AS READ
        // ============================================================================
        
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = true, updated_at = NOW()
            WHERE user_id = ? AND is_read = false
        ");
        $stmt->execute([$user['id']]);
        $updatedCount = $stmt->rowCount();
        
        // ============================================================================
        // LOG THE ACTIVITY
        // ============================================================================
        
        if ($updatedCount > 0) {
            try {
                $activityData = [
                    'user_id' => $user['id'],
                    'action' => 'notifications_marked_all_read',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'details' => json_encode(['count' => $updatedCount])
                ];
                Database::insert('activity_logs', $activityData);
            } catch (\Exception $e) {
                error_log("Failed to log activity: " . $e->getMessage());
            }
        }
        
        Response::success([
            'marked_count' => $updatedCount,
            'mark_all' => true
        ], "$updatedCount notification(s) marked as read");
        
    } else {
        // ============================================================================
        // MARK SINGLE NOTIFICATION AS READ
        // ============================================================================
        
        // Verify notification exists and belongs to user
        $stmt = $db->prepare("
            SELECT id, is_read FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $user['id']]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            Response::notFound('Notification');
        }
        
        if ($notification['is_read']) {
            Response::success([
                'notification_id' => $notificationId,
                'already_read' => true
            ], 'Notification already marked as read');
        }
        
        // Mark as read
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = true, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $user['id']]);
        
        // ============================================================================
        // LOG THE ACTIVITY
        // ============================================================================
        
        try {
            $activityData = [
                'user_id' => $user['id'],
                'action' => 'notification_marked_read',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => json_encode(['notification_id' => $notificationId])
            ];
            Database::insert('activity_logs', $activityData);
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        // ============================================================================
        // GET UPDATED UNREAD COUNT
        // ============================================================================
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = ? AND is_read = false
        ");
        $stmt->execute([$user['id']]);
        $unreadCount = $stmt->fetch()['unread_count'];
        
        Response::success([
            'notification_id' => $notificationId,
            'unread_count' => (int) $unreadCount,
            'mark_all' => false
        ], 'Notification marked as read');
    }
    
} catch (\PDOException $e) {
    error_log("Mark notifications read error: " . $e->getMessage());
    Response::serverError('Failed to mark notifications as read', false, $e);
}
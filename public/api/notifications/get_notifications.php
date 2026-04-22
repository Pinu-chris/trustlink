<?php
/**
 * TRUSTLINK - Get Notifications API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Retrieves all notifications for the authenticated user
 * Features:
 * - Pagination support
 * - Unread count
 * - Filter by read/unread status
 * - Mark all as read option
 * 
 * HTTP Method: GET
 * Endpoint: /api/notifications/get_notifications.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Query Parameters:
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 20)
 * - status: all, unread, read (default: all)
 * 
 * Response:
 * - 200: Notifications list with pagination and unread count
 * - 401: Unauthorized
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\NotificationType;
use TrustLink\Config\Pagination;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// ============================================================================
// PARSE QUERY PARAMETERS
// ============================================================================

$page = Pagination::validatePage($_GET['page'] ?? 1);
$perPage = Pagination::validatePerPage($_GET['per_page'] ?? 20);
$offset = Pagination::getOffset($page, $perPage);

$status = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'unread', 'read'];
if (!in_array($status, $allowedStatuses)) {
    $status = 'all';
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // GET UNREAD COUNT (for badge display)
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = false
    ");
    $stmt->execute([$user['id']]);
    $unreadCount = $stmt->fetch()['unread_count'];
    
    // ============================================================================
    // BUILD WHERE CLAUSE
    // ============================================================================
    
    $whereConditions = ["user_id = ?"];
    $params = [$user['id']];
    
    if ($status === 'unread') {
        $whereConditions[] = "is_read = false";
    } elseif ($status === 'read') {
        $whereConditions[] = "is_read = true";
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    // ============================================================================
    // GET TOTAL COUNT
    // ============================================================================
    
    $countSql = "SELECT COUNT(*) as total FROM notifications {$whereClause}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // ============================================================================
    // GET NOTIFICATIONS WITH PAGINATION
    // ============================================================================
    
    $sql = "
        SELECT 
            id, title, message, type, related_id, is_read, created_at
        FROM notifications 
        {$whereClause}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $queryParams = array_merge($params, [$perPage, $offset]);
    $stmt = $db->prepare($sql);
    $stmt->execute($queryParams);
    $notifications = $stmt->fetchAll();
    
    // ============================================================================
    // PROCESS NOTIFICATIONS
    // ============================================================================
    
    $processedNotifications = [];
    foreach ($notifications as $notification) {
        // Get type display name
        $typeDisplay = '';
        $typeIcon = '';
        
        switch ($notification['type']) {
            case NotificationType::ORDER_PLACED:
                $typeDisplay = 'New Order';
                $typeIcon = '🛒';
                break;
            case NotificationType::ORDER_ACCEPTED:
                $typeDisplay = 'Order Accepted';
                $typeIcon = '✅';
                break;
            case NotificationType::ORDER_COMPLETED:
                $typeDisplay = 'Order Completed';
                $typeIcon = '🎉';
                break;
            case NotificationType::ORDER_CANCELLED:
                $typeDisplay = 'Order Cancelled';
                $typeIcon = '❌';
                break;
            case NotificationType::REVIEW_RECEIVED:
                $typeDisplay = 'New Review';
                $typeIcon = '⭐';
                break;
            default:
                $typeDisplay = 'Notification';
                $typeIcon = '📢';
        }
        
        $processedNotifications[] = [
            'id' => $notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'type_display' => $typeDisplay,
            'type_icon' => $typeIcon,
            'related_id' => $notification['related_id'],
            'is_read' => (bool) $notification['is_read'],
            'created_at' => $notification['created_at'],
            'time_ago' => timeAgo($notification['created_at'])
        ];
    }
    
    // ============================================================================
    // PAGINATION METADATA
    // ============================================================================
    
    $totalPages = ceil($total / $perPage);
    
    $meta = [
        'pagination' => [
            'total' => (int) $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_previous' => $page > 1
        ],
        'unread_count' => (int) $unreadCount,
        'filter' => $status
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success([
        'notifications' => $processedNotifications,
        'meta' => $meta
    ], 'Notifications retrieved successfully');
    
} catch (\PDOException $e) {
    error_log("Get notifications error: " . $e->getMessage());
    Response::serverError('Failed to retrieve notifications', false, $e);
}

// ============================================================================
// HELPER FUNCTION: Time ago
// ============================================================================

function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    }
}
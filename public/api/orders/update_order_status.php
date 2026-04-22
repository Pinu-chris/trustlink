<?php
/**
 * TRUSTLINK - Update Order Status API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Updates order status (farmers only)
 * Features:
 * - Ownership validation
 * - Status flow validation
 * - Notification creation
 * - Completion timestamp tracking
 * 
 * HTTP Method: PUT
 * Endpoint: /api/orders/update_order_status.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "order_id": 123,
 *     "status": "accepted"
 * }
 * 
 * Response:
 * - 200: Status updated successfully
 * - 400: Invalid status
 * - 401: Unauthorized
 * - 403: Not owner or invalid transition
 * - 404: Order not found
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
use TrustLink\Config\OrderStatus;
use TrustLink\Config\NotificationType;
use TrustLink\Config\SuccessMessages;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require farmer role
$auth = new AuthMiddleware();
$user = $auth->requireFarmer();

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::badRequest('Invalid JSON payload');
}

// Validate required fields
$orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;
$newStatus = $input['status'] ?? null;

if ($orderId <= 0) {
    Response::badRequest('Order ID is required');
}

if (!$newStatus) {
    Response::badRequest('Status is required');
}

// Validate status
$allowedStatuses = [OrderStatus::ACCEPTED, OrderStatus::COMPLETED];
if (!in_array($newStatus, $allowedStatuses)) {
    Response::badRequest('Invalid status. Allowed: accepted, completed');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // FETCH ORDER WITH OWNERSHIP VALIDATION
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            o.id, o.order_code, o.status, o.buyer_id, o.farmer_id,
            u.name as buyer_name, u.phone as buyer_phone
        FROM orders o
        JOIN users u ON o.buyer_id = u.id
        WHERE o.id = ? AND o.farmer_id = ?
    ");
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        Response::notFound('Order');
    }
    
    // ============================================================================
    // VALIDATE STATUS TRANSITION
    // ============================================================================
    
    $currentStatus = $order['status'];
    
    // Define valid transitions
    $validTransitions = [
        OrderStatus::PENDING => [OrderStatus::ACCEPTED],
        OrderStatus::ACCEPTED => [OrderStatus::COMPLETED]
    ];
    
    if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
        Response::forbidden("Cannot change status from '{$currentStatus}' to '{$newStatus}'");
    }
    
    // ============================================================================
    // UPDATE ORDER STATUS
    // ============================================================================
    
    $completedAt = ($newStatus === OrderStatus::COMPLETED) ? 'NOW()' : 'NULL';
    
    $stmt = $db->prepare("
        UPDATE orders 
        SET status = ?, updated_at = NOW(), completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $newStatus, $orderId]);
    
    // ============================================================================
    // CREATE NOTIFICATION FOR BUYER
    // ============================================================================
    
    $notificationType = ($newStatus === OrderStatus::ACCEPTED) 
        ? NotificationType::ORDER_ACCEPTED 
        : NotificationType::ORDER_COMPLETED;
    
    $notificationMessage = NotificationType::getMessage($notificationType, [
        'order_code' => $order['order_code']
    ]);
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, related_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $order['buyer_id'],
        $newStatus === OrderStatus::ACCEPTED ? 'Order Accepted' : 'Order Completed',
        $notificationMessage,
        $notificationType,
        $orderId
    ]);
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $user['id'],
            'action' => 'order_status_updated',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode([
                'order_id' => $orderId,
                'order_code' => $order['order_code'],
                'old_status' => $currentStatus,
                'new_status' => $newStatus
            ])
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success([
        'order_id' => $orderId,
        'order_code' => $order['order_code'],
        'old_status' => $currentStatus,
        'new_status' => $newStatus,
        'status_display' => OrderStatus::displayName($newStatus)
    ], SuccessMessages::ORDER_ACCEPTED);
    
} catch (\PDOException $e) {
    error_log("Update order status error: " . $e->getMessage());
    Response::serverError('Failed to update order status', false, $e);
}
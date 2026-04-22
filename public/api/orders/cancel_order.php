<?php
/**
 * TRUSTLINK - Cancel Order API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Allows buyers to cancel pending orders
 * Features:
 * - Ownership validation
 * - Status validation (only pending)
 * - Stock restoration
 * - Notification creation
 * 
 * HTTP Method: POST
 * Endpoint: /api/orders/cancel_order.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "order_id": 123,
 *     "reason": "Changed my mind"
 * }
 * 
 * Response:
 * - 200: Order cancelled successfully
 * - 400: Invalid reason
 * - 401: Unauthorized
 * - 403: Cannot cancel (not owner or wrong status)
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
use TrustLink\Config\OrderStatus;
use TrustLink\Config\NotificationType;
use TrustLink\Config\SuccessMessages;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require buyer role
$auth = new AuthMiddleware();
$user = $auth->requireBuyer();

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::badRequest('Invalid JSON payload');
}

// Validate required fields
$orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;
$reason = trim($input['reason'] ?? '');

if ($orderId <= 0) {
    Response::badRequest('Order ID is required');
}

if (strlen($reason) > 500) {
    Response::badRequest('Reason cannot exceed 500 characters');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // FETCH ORDER WITH OWNERSHIP VALIDATION
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            o.id, o.order_code, o.status, o.buyer_id, o.farmer_id,
            u.name as farmer_name, u.phone as farmer_phone
        FROM orders o
        JOIN users u ON o.farmer_id = u.id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        Response::notFound('Order');
    }
    
    // ============================================================================
    // VALIDATE CANCEL CONDITION
    // ============================================================================
    
    if ($order['status'] !== OrderStatus::PENDING) {
        Response::forbidden("Cannot cancel order with status '{$order['status']}'. Only pending orders can be cancelled.");
    }
    
    // ============================================================================
    // START TRANSACTION
    // ============================================================================
    
    $db->beginTransaction();
    
    try {
        // ============================================================================
        // RESTORE STOCK
        // ============================================================================
        
        $stmt = $db->prepare("
            SELECT product_id, quantity FROM order_items WHERE order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll();
        
        foreach ($orderItems as $item) {
            $stmt = $db->prepare("
                UPDATE products 
                SET quantity = quantity + ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // ============================================================================
        // UPDATE ORDER STATUS
        // ============================================================================
        
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([OrderStatus::CANCELLED, $orderId]);
        
        // ============================================================================
        // CREATE NOTIFICATION FOR FARMER
        // ============================================================================
        
        $notificationMessage = NotificationType::getMessage(NotificationType::ORDER_CANCELLED, [
            'order_code' => $order['order_code']
        ]);
        
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $order['farmer_id'],
            'Order Cancelled',
            $notificationMessage . ($reason ? " Reason: {$reason}" : ""),
            NotificationType::ORDER_CANCELLED,
            $orderId
        ]);
        
        // ============================================================================
        // LOG THE ACTIVITY
        // ============================================================================
        
        try {
            $activityData = [
                'user_id' => $user['id'],
                'action' => 'order_cancelled',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => json_encode([
                    'order_id' => $orderId,
                    'order_code' => $order['order_code'],
                    'reason' => $reason
                ])
            ];
            Database::insert('activity_logs', $activityData);
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        // ============================================================================
        // COMMIT TRANSACTION
        // ============================================================================
        
        $db->commit();
        
        // ============================================================================
        // SUCCESS RESPONSE
        // ============================================================================
        
        Response::success([
            'order_id' => $orderId,
            'order_code' => $order['order_code'],
            'status' => OrderStatus::CANCELLED,
            'status_display' => OrderStatus::displayName(OrderStatus::CANCELLED),
            'reason' => $reason
        ], SuccessMessages::ORDER_CANCELLED);
        
    } catch (\Exception $e) {
        $db->rollBack();
        error_log("Cancel order transaction error: " . $e->getMessage());
        Response::serverError('Failed to cancel order', false, $e);
    }
    
} catch (\PDOException $e) {
    error_log("Cancel order error: " . $e->getMessage());
    Response::serverError('Failed to cancel order', false, $e);
}
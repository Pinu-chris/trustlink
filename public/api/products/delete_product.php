<?php
/**
 * TRUSTLINK - Delete Product API (Soft Delete)
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Soft deletes a product (hides from listings)
 * Features:
 * - Ownership validation
 * - Soft delete (status = false)
 * - Prevents deletion if product has pending orders
 * 
 * HTTP Method: DELETE
 * Endpoint: /api/products/delete_product.php?id=123
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Query Parameters:
 * - id: Product ID
 * 
 * Response:
 * - 200: Product deleted successfully
 * - 400: Missing product ID
 * - 401: Unauthorized
 * - 403: Not owner or has pending orders
 * - 404: Product not found
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

// Initialize auth and require farmer role
$auth = new AuthMiddleware();
$user = $auth->requireFarmer();

// Get product ID from query string
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productId <= 0) {
    Response::badRequest('Product ID is required');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // VERIFY PRODUCT OWNERSHIP
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT id, name, farmer_id, status
        FROM products 
        WHERE id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        Response::notFound('Product');
    }
    
    if ($product['farmer_id'] != $user['id']) {
        Response::forbidden('You do not own this product');
    }
    
    if (!$product['status']) {
        Response::conflict('Product is already deleted');
    }
    
    // ============================================================================
    // CHECK FOR PENDING ORDERS
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE oi.product_id = ? AND o.status IN ('pending', 'accepted')
    ");
    $stmt->execute([$productId]);
    $pendingOrders = $stmt->fetch()['count'];
    
    if ($pendingOrders > 0) {
        Response::forbidden("Cannot delete product with {$pendingOrders} pending order(s). Complete or cancel orders first.");
    }
    
    // ============================================================================
    // SOFT DELETE PRODUCT
    // ============================================================================
    
    $stmt = $db->prepare("
        UPDATE products 
        SET status = false, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$productId]);
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $user['id'],
            'action' => 'product_deleted',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode([
                'product_id' => $productId,
                'product_name' => $product['name']
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
        'product_id' => $productId,
        'product_name' => $product['name']
    ], SuccessMessages::PRODUCT_DELETED);
    
} catch (\PDOException $e) {
    error_log("Delete product error: " . $e->getMessage());
    Response::serverError('Failed to delete product', false, $e);
}
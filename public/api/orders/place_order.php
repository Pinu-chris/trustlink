<?php
/**
 * TRUSTLINK - Place Order API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Creates an order from user's cart
 * Features:
 * - Transaction management
 * - Stock validation and deduction
 * - Order code generation (handled by DB trigger)
 * - Notification creation
 * - Cart clearing after order
 * 
 * HTTP Method: POST
 * Endpoint: /api/orders/place_order.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "location": "Kilimani, Nairobi",
 *     "instructions": "Call before delivery",
 *     "payment_method": "cash"
 * }
 * 
 * Response:
 * - 201: Order placed successfully
 * - 400: Empty cart or validation errors
 * - 401: Unauthorized
 * - 403: Cannot order own products
 * - 409: Stock issues
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
use TrustLink\Config\PaymentStatus;
use TrustLink\Config\NotificationType;
use TrustLink\Config\SuccessMessages;
use TrustLink\Config\ErrorMessages;
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

// ============================================================================
// VALIDATE REQUIRED FIELDS
// ============================================================================

$errors = [];

$location = trim($input['location'] ?? '');
if (empty($location)) {
    $errors['location'] = 'Delivery location is required';
} elseif (strlen($location) > 255) {
    $errors['location'] = 'Location cannot exceed 255 characters';
}

$instructions = trim($input['instructions'] ?? '');
if (strlen($instructions) > 1000) {
    $errors['instructions'] = 'Instructions cannot exceed 1000 characters';
}

$paymentMethod = $input['payment_method'] ?? 'cash';
$allowedPaymentMethods = ['cash', 'mpesa'];
if (!in_array($paymentMethod, $allowedPaymentMethods)) {
    $errors['payment_method'] = 'Invalid payment method. Allowed: cash, mpesa';
}

if (!empty($errors)) {
    Response::validationError($errors, 'Validation failed');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // GET CART ITEMS
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            ci.id as cart_id,
            ci.product_id,
            ci.quantity as cart_quantity,
            p.name as product_name,
            p.price,
            p.quantity as stock_quantity,
            p.farmer_id,
            p.unit,
            u.name as farmer_name,
            u.phone as farmer_phone
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN users u ON p.farmer_id = u.id
        WHERE ci.user_id = ? AND p.status = true AND u.status = true
    ");
    $stmt->execute([$user['id']]);
    $cartItems = $stmt->fetchAll();
    
    if (empty($cartItems)) {
        Response::badRequest('Your cart is empty');
    }
    
    // ============================================================================
    // VALIDATE STOCK AND GROUP BY FARMER
    // ============================================================================
    
    $farmers = [];
    $stockIssues = [];
    $totalAmount = 0;
    $orderItems = [];
    
    foreach ($cartItems as $item) {
        // Check stock
        if ($item['stock_quantity'] < $item['cart_quantity']) {
            $stockIssues[] = "{$item['product_name']}: Only {$item['stock_quantity']} {$item['unit']} available";
            continue;
        }
        
        // Check if farmer is trying to buy own product (should not happen due to cart restrictions, but double-check)
        if ($item['farmer_id'] == $user['id']) {
            Response::forbidden('You cannot order your own products');
        }
        
        $subtotal = $item['price'] * $item['cart_quantity'];
        $totalAmount += $subtotal;
        
        // Group by farmer for order creation
        if (!isset($farmers[$item['farmer_id']])) {
            $farmers[$item['farmer_id']] = [
                'farmer_id' => $item['farmer_id'],
                'farmer_name' => $item['farmer_name'],
                'farmer_phone' => $item['farmer_phone'],
                'items' => [],
                'subtotal' => 0
            ];
        }
        
        $farmers[$item['farmer_id']]['items'][] = $item;
        $farmers[$item['farmer_id']]['subtotal'] += $subtotal;
        $orderItems[] = $item;
    }
    
    if (!empty($stockIssues)) {
        Response::conflict(ErrorMessages::PRODUCT_INSUFFICIENT_STOCK, [
            'issues' => $stockIssues
        ]);
    }
    
    if (empty($farmers)) {
        Response::badRequest('No valid items in cart');
    }
    
    // ============================================================================
    // CALCULATE DELIVERY FEE (per farmer)
    // ============================================================================
    
    $deliveryFeePerFarmer = 50; // Base delivery fee per farmer
    $totalDeliveryFee = count($farmers) * $deliveryFeePerFarmer;
    $grandTotal = $totalAmount + $totalDeliveryFee;
    
    // ============================================================================
    // START TRANSACTION
    // ============================================================================
    
    $db->beginTransaction();
    
    try {
        $orderIds = [];
        $notifications = [];
        
        // ============================================================================
        // CREATE ORDER FOR EACH FARMER
        // ============================================================================
        
        foreach ($farmers as $farmer) {
            // Insert order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    buyer_id, farmer_id, total, location, instructions,
                    payment_method, payment_status, delivery_fee, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                RETURNING id, order_code
            ");
            
            $stmt->execute([
                $user['id'],
                $farmer['farmer_id'],
                $farmer['subtotal'] + $deliveryFeePerFarmer,
                $location,
                $instructions,
                $paymentMethod,
                PaymentStatus::PENDING,
                $deliveryFeePerFarmer,
                OrderStatus::PENDING
            ]);
            
            $orderResult = $stmt->fetch();
            $orderId = $orderResult['id'];
            $orderCode = $orderResult['order_code'];
            $orderIds[] = $orderId;
            
            // ============================================================================
            // ADD ORDER ITEMS
            // ============================================================================
            
            foreach ($farmer['items'] as $item) {
                $subtotal = $item['price'] * $item['cart_quantity'];
                
                $stmt = $db->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, product_name, quantity, price, subtotal, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['cart_quantity'],
                    $item['price'],
                    $subtotal
                ]);
                
                // ============================================================================
                // DEDUCT STOCK
                // ============================================================================
                
                $stmt = $db->prepare("
                    UPDATE products 
                    SET quantity = quantity - ?, updated_at = NOW()
                    WHERE id = ? AND quantity >= ?
                ");
                $stmt->execute([$item['cart_quantity'], $item['product_id'], $item['cart_quantity']]);
                
                if ($stmt->rowCount() === 0) {
                    throw new \Exception("Stock deduction failed for product: {$item['product_name']}");
                }
            }
            
            // ============================================================================
            // CREATE NOTIFICATION FOR FARMER
            // ============================================================================
            
            $notificationMessage = NotificationType::getMessage(NotificationType::ORDER_PLACED, [
                'order_code' => $orderCode
            ]);
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $farmer['farmer_id'],
                'New Order Received',
                $notificationMessage,
                NotificationType::ORDER_PLACED,
                $orderId
            ]);
            
            $notifications[] = [
                'farmer_id' => $farmer['farmer_id'],
                'order_code' => $orderCode
            ];
        }
        
        // ============================================================================
        // CLEAR CART
        // ============================================================================
        
        $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        
        // ============================================================================
        // COMMIT TRANSACTION
        // ============================================================================
        
        $db->commit();
        
        // ============================================================================
        // LOG THE ACTIVITY
        // ============================================================================
        
        try {
            $activityData = [
                'user_id' => $user['id'],
                'action' => 'order_placed',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => json_encode([
                    'order_count' => count($orderIds),
                    'total_amount' => $grandTotal,
                    'farmer_count' => count($farmers)
                ])
            ];
            Database::insert('activity_logs', $activityData);
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        // ============================================================================
        // SUCCESS RESPONSE
        // ============================================================================
        
        Response::created([
            'orders' => $orderIds,
            'order_count' => count($orderIds),
            'subtotal' => (float) $totalAmount,
            'delivery_fee' => (float) $totalDeliveryFee,
            'total' => (float) $grandTotal,
            'notifications_sent' => count($notifications)
        ], SuccessMessages::ORDER_PLACED);
        
    } catch (\Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        error_log("Place order transaction error: " . $e->getMessage());
        Response::serverError('Failed to place order. Please try again.', false, $e);
    }
    
} catch (\PDOException $e) {
    error_log("Place order error: " . $e->getMessage());
    Response::serverError('Failed to place order', false, $e);
}
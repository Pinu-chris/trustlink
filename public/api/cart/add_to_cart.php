<?php
/**
 * TRUSTLINK - Add to Cart API
 * Version: 1.1 | Production Ready | April 2026
 * 
 * Description: Adds a product to user's shopping cart
 * Features:
 * - Stock validation before adding
 * - Quantity validation
 * - Update existing cart item if product already in cart
 * - Prevent farmers from adding their own products to cart
 * - Safe database queries with table aliases to avoid ambiguous columns
 * 
 * HTTP Method: POST
 * Endpoint: /api/cart/add_to_cart.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "product_id": 123,
 *     "quantity": 2
 * }
 * 
 * Response:
 * - 200: Item added/updated successfully
 * - 400: Validation errors
 * - 401: Unauthorized
 * - 403: Cannot add own product
 * - 404: Product not found
 * - 409: Out of stock
 */

// Disable error display to avoid HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// Only buyers can add to cart (optional: you can allow any logged-in user)
$auth->requireBuyer();

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    Response::badRequest('Invalid JSON payload');
}

// Validate required fields
if (empty($input['product_id'])) {
    Response::validationError(['product_id' => 'Product ID is required'], 'Validation failed');
}

$productId = (int) $input['product_id'];
$quantity = isset($input['quantity']) ? (int) $input['quantity'] : 1;

// Validate quantity
if ($quantity <= 0) {
    Response::validationError(['quantity' => 'Quantity must be at least 1'], 'Validation failed');
}

$maxQuantity = 100;
if ($quantity > $maxQuantity) {
    Response::validationError(['quantity' => "Quantity cannot exceed $maxQuantity"], 'Validation failed');
}

try {
    $db = Database::getInstance();
    
    // Check if product exists and is active
    $stmt = $db->prepare("
        SELECT id, farmer_id, name, price, quantity AS stock, status, unit
        FROM products 
        WHERE id = ? AND status = true
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        Response::notFound('Product');
    }
    
    // Prevent farmers from adding their own products
    if ($product['farmer_id'] == $user['id']) {
        Response::forbidden('You cannot add your own products to cart');
    }
    
    // Check stock availability
    if ($product['stock'] < $quantity) {
        Response::conflict("Insufficient stock. Available: {$product['stock']} {$product['unit']}");
    }
    
    // Check if product already exists in cart
    $stmt = $db->prepare("
        SELECT id, quantity FROM cart_items 
        WHERE user_id = ? AND product_id = ?
    ");
    $stmt->execute([$user['id'], $productId]);
    $existingItem = $stmt->fetch();
    
    if ($existingItem) {
        // Update existing cart item
        $newQuantity = $existingItem['quantity'] + $quantity;
        if ($product['stock'] < $newQuantity) {
            Response::conflict("Cannot add more. Only {$product['stock']} {$product['unit']} available");
        }
        $stmt = $db->prepare("
            UPDATE cart_items 
            SET quantity = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newQuantity, $existingItem['id']]);
        $action = 'updated';
        $message = "Product quantity updated in cart";
        $finalQuantity = $newQuantity;
    } else {
        // Add new cart item
        $stmt = $db->prepare("
            INSERT INTO cart_items (user_id, product_id, quantity, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user['id'], $productId, $quantity]);
        $action = 'added';
        $message = "Product added to cart";
        $finalQuantity = $quantity;
    }
    
    // Optional: Log activity (skip if table doesn't exist)
    try {
        $tableCheck = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'activity_logs')");
        if ($tableCheck->fetchColumn()) {
            $logStmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, ip_address, user_agent, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $user['id'],
                'cart_' . $action,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                json_encode([
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'quantity' => $quantity
                ])
            ]);
        }
    } catch (\Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // Get updated cart summary (fix ambiguous column by using table aliases)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) AS item_count,
            COALESCE(SUM(ci.quantity * p.price), 0) AS total
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $cartSummary = $stmt->fetch();
    
    // Success response
    Response::success([
        'cart_item' => [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'quantity' => $finalQuantity,
            'unit' => $product['unit'],
            'price' => (float) $product['price'],
            'subtotal' => (float) ($product['price'] * $finalQuantity)
        ],
        'cart_summary' => [
            'item_count' => (int) $cartSummary['item_count'],
            'total' => (float) $cartSummary['total']
        ]
    ], $message);
    
} catch (\PDOException $e) {
    error_log("Add to cart PDO error: " . $e->getMessage());
    Response::serverError('Failed to add item to cart');
} catch (\Exception $e) {
    error_log("Add to cart general error: " . $e->getMessage());
    Response::serverError('An unexpected error occurred');
}
?>
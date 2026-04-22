<?php
/**
 * TRUSTLINK - Update Product API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Allows farmers to update their existing products
 * Features:
 * - Ownership validation
 * - Partial updates (only provided fields)
 * - Stock management
 * 
 * HTTP Method: PUT
 * Endpoint: /api/products/update_product.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "product_id": 123,
 *     "name": "Updated Product Name",
 *     "price": 60.00,
 *     "quantity": 150
 * }
 * 
 * Response:
 * - 200: Product updated successfully
 * - 400: Validation errors
 * - 401: Unauthorized
 * - 403: Not owner
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
use TrustLink\Config\ProductCategory;
use TrustLink\Config\UnitType;
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

// Validate product ID
$productId = isset($input['product_id']) ? (int) $input['product_id'] : 0;
if ($productId <= 0) {
    Response::badRequest('Product ID is required');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // VERIFY PRODUCT OWNERSHIP
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT id, name, farmer_id, quantity, status
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
    
    // ============================================================================
    // FIELD VALIDATION AND BUILD UPDATE
    // ============================================================================
    
    $errors = [];
    $updateData = [];
    
    // Name validation
    if (isset($input['name'])) {
        $name = trim($input['name']);
        if (strlen($name) < 3) {
            $errors['name'] = 'Product name must be at least 3 characters';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Product name cannot exceed 100 characters';
        } else {
            $updateData['name'] = $name;
        }
    }
    
    // Category validation
    if (isset($input['category'])) {
        $category = $input['category'];
        if (!in_array($category, ProductCategory::all())) {
            $errors['category'] = 'Invalid category';
        } else {
            $updateData['category'] = $category;
        }
    }
    
    // Price validation
    if (isset($input['price'])) {
        $price = (float) $input['price'];
        if ($price <= 0) {
            $errors['price'] = 'Price must be greater than 0';
        } elseif ($price > 100000) {
            $errors['price'] = 'Price cannot exceed 100,000 KES';
        } else {
            $updateData['price'] = $price;
        }
    }
    
    // Quantity validation
    if (isset($input['quantity'])) {
        $quantity = (int) $input['quantity'];
        if ($quantity < 0) {
            $errors['quantity'] = 'Quantity cannot be negative';
        } elseif ($quantity > 999999) {
            $errors['quantity'] = 'Quantity cannot exceed 999,999';
        } else {
            $updateData['quantity'] = $quantity;
        }
    }
    
    // Unit validation
    if (isset($input['unit'])) {
        $unit = $input['unit'];
        if (!in_array($unit, UnitType::all())) {
            $errors['unit'] = 'Invalid unit';
        } else {
            $updateData['unit'] = $unit;
        }
    }
    
    // Description validation
    if (isset($input['description'])) {
        $description = trim($input['description']);
        if (strlen($description) > 5000) {
            $errors['description'] = 'Description cannot exceed 5000 characters';
        } else {
            $updateData['description'] = $description;
        }
    }
    
    // Return validation errors
    if (!empty($errors)) {
        Response::validationError($errors, 'Validation failed');
    }
    
    // If no fields to update
    if (empty($updateData)) {
        Response::success(null, 'No changes to update');
    }
    
    // ============================================================================
    // UPDATE PRODUCT
    // ============================================================================
    
    $setClause = [];
    $params = [];
    
    foreach ($updateData as $column => $value) {
        $setClause[] = "$column = ?";
        $params[] = $value;
    }
    
    $params[] = $productId;
    $sql = "UPDATE products SET " . implode(', ', $setClause) . ", updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $user['id'],
            'action' => 'product_updated',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode([
                'product_id' => $productId,
                'product_name' => $product['name'],
                'updated_fields' => array_keys($updateData)
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
        'updated_fields' => array_keys($updateData)
    ], SuccessMessages::PRODUCT_UPDATED);
    
} catch (\PDOException $e) {
    error_log("Update product error: " . $e->getMessage());
    Response::serverError('Failed to update product', false, $e);
}
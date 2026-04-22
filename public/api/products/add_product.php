<?php
/**
 * TRUSTLINK - Add Product API (PostgreSQL Version)
 * Version: 2.0 | Production Ready | March 2026
 */

// ============================================================================
// CRITICAL: No output before this point
// ============================================================================

// Turn off error display to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

// ============================================================================
// Session Configuration - MUST be before any output
// ============================================================================

// Only configure if session not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name('trustlink_session');
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Clear any output buffers
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// ============================================================================
// Load required files
// ============================================================================

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\ProductCategory;
use TrustLink\Config\UnitType;
use TrustLink\Config\SuccessMessages;

// ============================================================================
// Helper function to send JSON errors
// ============================================================================

function sendJsonError($message, $code = 400, $errors = null) {
    http_response_code($code);
    $response = [
        'success' => false,
        'message' => $message,
        'status_code' => $code
    ];
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
    exit;
}

// ============================================================================
// Authentication Check
// ============================================================================

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonError('You are not logged in. Please login first.', 401);
}

// Check if user is a farmer
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'farmer') {
    sendJsonError('Only farmers can add products.', 403);
}

// ============================================================================
// Get and validate input
// ============================================================================

// Get raw input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonError('Invalid JSON payload. Please send valid JSON.', 400);
}

// Validate required fields
$errors = [];

$name = trim($input['name'] ?? '');
$category = $input['category'] ?? 'other';
$price = isset($input['price']) ? (float) $input['price'] : 0;
$quantity = isset($input['quantity']) ? (int) $input['quantity'] : 0;
$unit = $input['unit'] ?? 'piece';
$description = trim($input['description'] ?? '');

// Name validation
if (empty($name)) {
    $errors['name'] = 'Product name is required';
} elseif (strlen($name) < 3) {
    $errors['name'] = 'Product name must be at least 3 characters';
} elseif (strlen($name) > 100) {
    $errors['name'] = 'Product name cannot exceed 100 characters';
}

// Category validation
$allowedCategories = ProductCategory::all();
if (!in_array($category, $allowedCategories)) {
    $errors['category'] = 'Invalid category. Allowed: ' . implode(', ', $allowedCategories);
}

// Price validation
if ($price <= 0) {
    $errors['price'] = 'Price must be greater than 0';
} elseif ($price > 100000) {
    $errors['price'] = 'Price cannot exceed 100,000 KES';
}

// Quantity validation
if ($quantity <= 0) {
    $errors['quantity'] = 'Quantity must be greater than 0';
} elseif ($quantity > 999999) {
    $errors['quantity'] = 'Quantity cannot exceed 999,999';
}

// Unit validation
$allowedUnits = UnitType::all();
if (!in_array($unit, $allowedUnits)) {
    $errors['unit'] = 'Invalid unit. Allowed: ' . implode(', ', $allowedUnits);
}

// Return validation errors if any
if (!empty($errors)) {
    sendJsonError('Validation failed', 422, $errors);
}

// ============================================================================
// Insert Product (PostgreSQL Compatible)
// ============================================================================

try {
    $db = Database::getInstance();
    
    // Check for duplicate product (PostgreSQL syntax)
    $stmt = $db->prepare("
        SELECT id FROM products 
        WHERE farmer_id = ? AND LOWER(name) = LOWER(?) AND status = true
    ");
    $stmt->execute([$_SESSION['user_id'], $name]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        sendJsonError('You already have a product with this name. Please use a different name.', 409);
    }
    
    // Insert product - NOTE: status is BOOLEAN, so use TRUE not 'active'
    // category is ENUM, so use the string value directly
    $stmt = $db->prepare("
        INSERT INTO products (
            farmer_id, 
            name, 
            category, 
            description, 
            price, 
            quantity, 
            unit, 
            status, 
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, NOW(), NOW())
        RETURNING id
    ");
    
    $result = $stmt->execute([
        $_SESSION['user_id'],
        $name,
        $category,
        $description,
        $price,
        $quantity,
        $unit
    ]);
    
    if ($result) {
        // Get the inserted ID using PostgreSQL's RETURNING
        $row = $stmt->fetch();
        $productId = $row['id'];
        
        // Log activity (optional - skip if activity_logs table doesn't exist)
        try {
            // Check if activity_logs table exists
            $checkTable = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'activity_logs')");
            $tableExists = $checkTable->fetchColumn();
            
            if ($tableExists) {
                $logStmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, ip_address, user_agent, details, created_at)
                    VALUES (?, 'product_added', ?, ?, ?, NOW())
                ");
                $logStmt->execute([
                    $_SESSION['user_id'],
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    json_encode([
                        'product_id' => $productId,
                        'product_name' => $name,
                        'price' => $price,
                        'quantity' => $quantity
                    ])
                ]);
            }
        } catch (Exception $e) {
            // Log but don't fail the request
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        // Success response
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => SuccessMessages::PRODUCT_ADDED,
            'status_code' => 201,
            'data' => [
                'product' => [
                    'id' => $productId,
                    'name' => $name,
                    'category' => $category,
                    'category_display' => ProductCategory::displayName($category),
                    'description' => $description,
                    'price' => (float) $price,
                    'quantity' => (int) $quantity,
                    'unit' => $unit,
                    'unit_display' => UnitType::displayName($unit),
                    'unit_abbr' => UnitType::abbreviation($unit),
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]
        ]);
        exit;
        
    } else {
        sendJsonError('Failed to add product. Please try again.', 500);
    }
    
} catch (PDOException $e) {
    // Log the full error for debugging
    error_log("Add product PDO error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    
    // Send user-friendly error
    sendJsonError('Database error occurred. Please try again.', 500);
    
} catch (Exception $e) {
    error_log("Add product general error: " . $e->getMessage());
    sendJsonError('An error occurred. Please try again.', 500);
}
?>
<?php
/**
 * TRUSTLINK - Update Cart Item API
 * Version: 1.1 | Production Ready | April 2026
 * 
 * Updates the quantity of a product in the user's cart.
 * 
 * HTTP Method: PUT
 * Endpoint: /api/cart/update_cart.php
 * 
 * Request Body (JSON):
 * {
 *     "cart_item_id": 123,
 *     "quantity": 3
 * }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$user = $auth->requireAuth();
$auth->requireBuyer();

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$cartItemId = isset($input['cart_item_id']) ? (int) $input['cart_item_id'] : 0;
$newQuantity = isset($input['quantity']) ? (int) $input['quantity'] : 0;

if ($cartItemId <= 0) {
    Response::badRequest('Cart item ID is required');
}
if ($newQuantity <= 0) {
    Response::badRequest('Quantity must be at least 1');
}

try {
    $db = Database::getInstance();

    // Verify the cart item belongs to the user and get product details
    $stmt = $db->prepare("
        SELECT ci.id, ci.quantity as current_qty, ci.product_id, p.price, p.quantity as stock, p.name, p.unit
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.id = ? AND ci.user_id = ?
    ");
    $stmt->execute([$cartItemId, $user['id']]);
    $cartItem = $stmt->fetch();

    if (!$cartItem) {
        Response::notFound('Cart item');
    }

    // Check stock
    if ($newQuantity > $cartItem['stock']) {
        Response::badRequest("Not enough stock. Only {$cartItem['stock']} {$cartItem['unit']} available.");
    }

    // Update quantity
    $stmt = $db->prepare("
        UPDATE cart_items
        SET quantity = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newQuantity, $cartItemId]);

    // Get updated cart summary (using aliases to avoid ambiguity)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) AS item_count,
            COALESCE(SUM(ci.quantity * p.price), 0) AS total
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $summary = $stmt->fetch();

    // Return success with new cart totals
    Response::success([
        'cart_item_id' => $cartItemId,
        'new_quantity' => $newQuantity,
        'cart_summary' => [
            'item_count' => (int) $summary['item_count'],
            'total' => (float) $summary['total']
        ]
    ], 'Cart updated');

} catch (\PDOException $e) {
    error_log("Update cart error: " . $e->getMessage());
    Response::serverError('Failed to update cart', false, $e);
}
?>
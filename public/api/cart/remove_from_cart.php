<?php
/**
 * TRUSTLINK - Remove from Cart API
 * Version: 1.0 | Production Ready | April 2026
 * 
 * Removes a product from the user's cart.
 * 
 * HTTP Method: DELETE
 * Endpoint: /api/cart/remove_from_cart.php?cart_item_id=123
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

// Get cart_item_id from query string
$cartItemId = isset($_GET['cart_item_id']) ? (int) $_GET['cart_item_id'] : 0;

if ($cartItemId <= 0) {
    Response::badRequest('Cart item ID is required');
}

try {
    $db = Database::getInstance();

    // Verify the cart item belongs to the user
    $stmt = $db->prepare("
        SELECT id FROM cart_items
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$cartItemId, $user['id']]);
    $cartItem = $stmt->fetch();

    if (!$cartItem) {
        Response::notFound('Cart item');
    }

    // Delete the item
    $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ?");
    $stmt->execute([$cartItemId]);

    // Get updated cart summary
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

    Response::success([
        'cart_summary' => [
            'item_count' => (int) $summary['item_count'],
            'total' => (float) $summary['total']
        ]
    ], 'Item removed from cart');

} catch (\PDOException $e) {
    error_log("Remove from cart error: " . $e->getMessage());
    Response::serverError('Failed to remove item', false, $e);
}
?>
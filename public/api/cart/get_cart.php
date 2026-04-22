<?php
/**
 * TRUSTLINK - Get Cart API (Fixed)
 * Version: 1.2 | April 2026
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

try {
    $db = Database::getInstance();

    // Get cart items
    $stmt = $db->prepare("
        SELECT 
            ci.id as cart_item_id,
            ci.quantity as cart_quantity,
            ci.created_at as added_at,
            p.id as product_id,
            p.name as product_name,
            p.price as current_price,
            p.quantity as stock_quantity,
            p.unit,
            p.status as product_status,
            u.id as farmer_id,
            u.name as farmer_name,
            u.trust_score as farmer_trust_score,
            u.verification_tier as farmer_verification_tier
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN users u ON p.farmer_id = u.id
        WHERE ci.user_id = ?
        ORDER BY ci.created_at ASC
    ");
    $stmt->execute([$user['id']]);
    $cartItems = $stmt->fetchAll();

    // Helper function for unit abbreviations
    function getUnitAbbreviation($unit) {
        $map = [
            'kilogram' => 'kg',
            'gram' => 'g',
            'litre' => 'L',
            'millilitre' => 'mL',
            'piece' => 'pc',
            'bunch' => 'bunch',
            'bundle' => 'bundle',
            'bag' => 'bag'
        ];
        return $map[strtolower($unit)] ?? $unit;
    }

    // Base URL from environment
    $baseUrl = getenv('APP_URL') ?: 'http://localhost/trustfiles';
    // Clean up any trailing slashes
    $baseUrl = rtrim($baseUrl, '/');

    $items = [];
    $subtotal = 0;
    $invalidItems = 0;
    $stockIssues = [];

    foreach ($cartItems as $item) {
        $isAvailable = ($item['product_status'] && $item['stock_quantity'] > 0);
        $isInStock = $item['stock_quantity'] >= $item['cart_quantity'];

        $itemSubtotal = $item['current_price'] * $item['cart_quantity'];
        $subtotal += $itemSubtotal;

        if (!$isAvailable || !$isInStock) {
            $invalidItems++;
            if (!$isAvailable) {
                $stockIssues[] = "{$item['product_name']} is no longer available";
            } elseif (!$isInStock) {
                $stockIssues[] = "Only {$item['stock_quantity']} {$item['unit']} of {$item['product_name']} available";
            }
        }

        // Get primary image filename
        $stmtImg = $db->prepare("
            SELECT image_url FROM product_images
            WHERE product_id = ? AND is_primary = true
            LIMIT 1
        ");
        $stmtImg->execute([$item['product_id']]);
        $imageFilename = $stmtImg->fetchColumn();

        // Build full image URL (if filename exists)
        $imageUrl = $imageFilename ? $baseUrl . '/assets/images/uploads/products/' . $imageFilename : null;

        $items[] = [
            'cart_item_id' => $item['cart_item_id'],
            'product' => [
                'id' => $item['product_id'],
                'name' => $item['product_name'],
                'price' => (float) $item['current_price'],
                'unit' => $item['unit'],
                'unit_abbr' => getUnitAbbreviation($item['unit']),
                'stock_quantity' => (int) $item['stock_quantity'],
                'is_available' => (bool) $isAvailable,
                'is_in_stock' => (bool) $isInStock,
                'images' => $imageUrl ? [$imageUrl] : [],
                'farmer' => [
                    'id' => $item['farmer_id'],
                    'name' => $item['farmer_name'],
                    'trust_score' => (float) $item['farmer_trust_score'],
                    'verification_tier' => $item['farmer_verification_tier']
                ]
            ],
            'quantity' => (int) $item['cart_quantity'],
            'subtotal' => (float) $itemSubtotal,
            'added_at' => $item['added_at']
        ];
    }

    $deliveryFee = ($subtotal > 0) ? 50 : 0;
    $total = $subtotal + $deliveryFee;

    $suggestions = [];
    if ($invalidItems > 0) {
        $suggestions[] = [
            'type' => 'remove_invalid_items',
            'message' => 'Some items in your cart are no longer available or out of stock',
            'issues' => $stockIssues
        ];
    }

    $summary = [
        'item_count' => count($items),
        'subtotal' => (float) $subtotal,
        'delivery_fee' => (float) $deliveryFee,
        'total' => (float) $total,
        'has_invalid_items' => $invalidItems > 0,
        'suggestions' => $suggestions
    ];

    Response::success([
        'items' => $items,
        'summary' => $summary
    ], 'Cart retrieved successfully');

} catch (\PDOException $e) {
    error_log("Get cart error: " . $e->getMessage());
    Response::serverError('Failed to retrieve cart', false, $e);
}
?>
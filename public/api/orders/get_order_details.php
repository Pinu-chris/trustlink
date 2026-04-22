<?php
/**
 * TRUSTLINK - Get Order Details API
 * Returns full order details including items
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

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($orderId <= 0) {
    Response::badRequest('Order ID is required');
}

try {
    $db = Database::getInstance();

    // Check if user has access (buyer or farmer of this order)
    $stmt = $db->prepare("
        SELECT o.*, u.name as farmer_name, u.phone as farmer_phone, u.trust_score as farmer_trust_score
        FROM orders o
        JOIN users u ON o.farmer_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        Response::notFound('Order');
    }

    // Authorization: buyer, farmer, or admin
    $isBuyer = ($order['buyer_id'] == $user['id']);
    $isFarmer = ($order['farmer_id'] == $user['id']);
    $isAdmin = ($user['role'] === 'admin');

    if (!$isBuyer && !$isFarmer && !$isAdmin) {
        Response::forbidden('You do not have permission to view this order');
    }

    // Get order items with product details
    $stmt = $db->prepare("
        SELECT oi.*, p.name as product_name, p.price as product_price, p.unit,
               pi.image_url as primary_image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    $processedItems = [];
    $subtotal = 0;
    foreach ($items as $item) {
        $itemSubtotal = $item['quantity'] * $item['price'];
        $subtotal += $itemSubtotal;
        $processedItems[] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => (int) $item['quantity'],
            'price' => (float) $item['price'],
            'subtotal' => (float) $itemSubtotal,
            'image_url' => $item['primary_image'] 
                ? (getenv('APP_URL') ?: 'http://localhost/trustfiles') . '/assets/images/uploads/products/' . $item['primary_image']
                : null
        ];
    }

    // Check if a review already exists for this order (from buyer)
    $stmt = $db->prepare("
        SELECT id FROM reviews
        WHERE order_id = ? AND buyer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, $user['id']]);
    $reviewExists = $stmt->fetch();

    $response = [
        'id' => $order['id'],
        'order_code' => $order['order_code'],
        'total' => (float) $order['total'],
        'delivery_fee' => (float) ($order['delivery_fee'] ?? 50),
        'subtotal' => (float) $subtotal,
        'location' => $order['location'],
        'instructions' => $order['instructions'],
        'status' => $order['status'],
        'status_display' => ucfirst($order['status']),
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'],
        'created_at' => $order['created_at'],
        'completed_at' => $order['completed_at'],
        'farmer' => [
            'id' => $order['farmer_id'],
            'name' => $order['farmer_name'],
            'phone' => $order['farmer_phone'],
            'trust_score' => (float) $order['farmer_trust_score']
        ],
        'items' => $processedItems,
        'review_submitted' => (bool) $reviewExists
    ];

    Response::success($response, 'Order details retrieved');

} catch (\PDOException $e) {
    error_log("Get order details error: " . $e->getMessage());
    Response::serverError('Failed to retrieve order details');
}
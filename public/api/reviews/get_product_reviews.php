<?php
/**
 * Get product reviews – all reviews for a specific product
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;

$productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
if ($productId <= 0) {
    Response::badRequest('Product ID is required');
}

try {
    $db = Database::getInstance();

    // Get reviews for this product (via order_items) – include r.id
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.rating,
            r.comment,
            r.created_at,
            r.farmer_reply,
            r.farmer_replied_at,
            u.name as buyer_name
        FROM reviews r
        JOIN orders o ON r.order_id = o.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN users u ON r.buyer_id = u.id
        WHERE oi.product_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();

    // Calculate average rating for this product
    $stmt = $db->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total
        FROM reviews r
        JOIN orders o ON r.order_id = o.id
        JOIN order_items oi ON o.id = oi.order_id
        WHERE oi.product_id = ?
    ");
    $stmt->execute([$productId]);
    $summary = $stmt->fetch();

    // Process reviews
    $processed = [];
    foreach ($reviews as $r) {
        $processed[] = [
            'id' => $r['id'],
            'buyer_name' => $r['buyer_name'],
            'rating' => (int) $r['rating'],
            'comment' => $r['comment'],
            'created_at' => $r['created_at'],
            'time_ago' => time_ago($r['created_at']),
            'farmer_reply' => $r['farmer_reply'],
            'farmer_replied_at' => $r['farmer_replied_at']
        ];
    }

    Response::success([
        'rating_summary' => [
            'average' => round($summary['avg_rating'] ?: 0, 1),
            'total' => (int) $summary['total']
        ],
        'reviews' => $processed
    ], 'Reviews retrieved');

} catch (\PDOException $e) {
    error_log("Get product reviews error: " . $e->getMessage());
    Response::serverError('Failed to retrieve reviews');
}

function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $timestamp);
}
?>
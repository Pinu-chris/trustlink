<?php
/**
 * TRUSTLINK - Add Review API
 * Allows a buyer to leave a review for a farmer after order completion
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$user = $auth->requireAuth();
$auth->requireBuyer(); // only buyers can review

$input = json_decode(file_get_contents('php://input'), true);
$orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;
$farmerId = isset($input['farmer_id']) ? (int) $input['farmer_id'] : 0;
$rating = isset($input['rating']) ? (int) $input['rating'] : 0;
$comment = trim($input['comment'] ?? '');

if (!$orderId || !$farmerId) {
    Response::badRequest('Order ID and Farmer ID are required');
}
if ($rating < 1 || $rating > 5) {
    Response::badRequest('Rating must be between 1 and 5');
}
if (strlen($comment) > 500) {
    Response::badRequest('Comment cannot exceed 500 characters');
}

try {
    $db = Database::getInstance();

    // Verify that the order belongs to this buyer and is completed
    $stmt = $db->prepare("
        SELECT id, status, farmer_id FROM orders
        WHERE id = ? AND buyer_id = ? AND status = 'completed'
    ");
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();

    if (!$order) {
        Response::badRequest('Order not found or not completed');
    }
    if ($order['farmer_id'] != $farmerId) {
        Response::badRequest('Farmer does not match order');
    }

    // Check if a review already exists for this order
    $stmt = $db->prepare("SELECT id FROM reviews WHERE order_id = ? AND buyer_id = ?");
    $stmt->execute([$orderId, $user['id']]);
    if ($stmt->fetch()) {
        Response::badRequest('You have already reviewed this order');
    }

    // Insert review – note the column is seller_id, not farmer_id
    $stmt = $db->prepare("
        INSERT INTO reviews (order_id, buyer_id, seller_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        RETURNING id
    ");
    $stmt->execute([$orderId, $user['id'], $farmerId, $rating, $comment]);
    $reviewId = $stmt->fetchColumn();

    // Update farmer's trust score (average of all reviews for that farmer)
    $stmt = $db->prepare("
        UPDATE users u
        SET trust_score = (
            SELECT AVG(rating) FROM reviews WHERE seller_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$farmerId, $farmerId]);

    Response::success([
        'review_id' => $reviewId,
        'rating' => $rating,
        'comment' => $comment
    ], 'Review submitted successfully');

} catch (\PDOException $e) {
    error_log("Add review error: " . $e->getMessage());
    Response::serverError('Failed to submit review');
}
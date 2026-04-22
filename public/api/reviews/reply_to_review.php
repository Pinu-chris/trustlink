<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$user = $auth->requireAuth();
$auth->requireFarmer();

$input = json_decode(file_get_contents('php://input'), true);
$reviewId = (int)($input['review_id'] ?? 0);
$reply = trim($input['reply'] ?? '');

if (!$reviewId || !$reply) {
    Response::badRequest('Review ID and reply are required');
}
if (strlen($reply) > 500) {
    Response::badRequest('Reply cannot exceed 500 characters');
}

try {
    $db = Database::getInstance();

    // Verify the review belongs to a product owned by this farmer
    $stmt = $db->prepare("
        SELECT r.id FROM reviews r
        JOIN orders o ON r.order_id = o.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE r.id = ? AND p.farmer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$reviewId, $user['id']]);
    if (!$stmt->fetch()) {
        Response::forbidden('You are not allowed to reply to this review');
    }

    // Update the review
    $stmt = $db->prepare("
        UPDATE reviews SET farmer_reply = ?, farmer_replied_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$reply, $reviewId]);

    Response::success(['review_id' => $reviewId], 'Reply added successfully');

} catch (\PDOException $e) {
    error_log("Reply to review error: " . $e->getMessage());
    Response::serverError('Failed to add reply');
}
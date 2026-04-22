<?php
/**
 * TRUSTLINK - Get Buyer Stats API
 * Returns order statistics for the logged-in buyer
 * 
 * HTTP Method: GET
 * Endpoint: /api/users/get_buyer_stats.php
 * 
 * Response:
 * - 200: { success: true, data: { total_orders, completed_orders, pending_orders, total_spent } }
 */

// Set JSON header
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// Ensure the user is a buyer
if ($user['role'] !== 'buyer') {
    Response::forbidden('Only buyers can access this endpoint');
}

try {
    $db = Database::getInstance();

    // Total orders count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE buyer_id = ?");
    $stmt->execute([$user['id']]);
    $totalOrders = (int) $stmt->fetch()['total'];

    // Completed orders count (status = 'completed')
    $stmt = $db->prepare("SELECT COUNT(*) as completed FROM orders WHERE buyer_id = ? AND status = 'completed'");
    $stmt->execute([$user['id']]);
    $completedOrders = (int) $stmt->fetch()['completed'];

    // Pending orders count (status = 'pending')
    $stmt = $db->prepare("SELECT COUNT(*) as pending FROM orders WHERE buyer_id = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    $pendingOrders = (int) $stmt->fetch()['pending'];

    // Total spent (sum of totals for completed orders)
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total_spent FROM orders WHERE buyer_id = ? AND status = 'completed'");
    $stmt->execute([$user['id']]);
    $totalSpent = (float) $stmt->fetch()['total_spent'];

    // Return stats
    Response::success([
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'pending_orders' => $pendingOrders,
        'total_spent' => $totalSpent
    ], 'Buyer stats retrieved successfully');

} catch (\PDOException $e) {
    error_log("Get buyer stats error: " . $e->getMessage());
    Response::serverError('Failed to retrieve buyer stats');
}
<?php
/**
 * Get farmer stats (total orders, completed orders, total earnings)
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
$auth->requireFarmer();

try {
    $db = Database::getInstance();

    // Total orders for this farmer
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_orders
        FROM orders
        WHERE farmer_id = ?
    ");
    $stmt->execute([$user['id']]);
    $totalOrders = $stmt->fetch()['total_orders'];

    // Completed orders
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed_orders
        FROM orders
        WHERE farmer_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $completedOrders = $stmt->fetch()['completed_orders'];

    // Total earnings (sum of totals for completed orders)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total), 0) as total_earnings
        FROM orders
        WHERE farmer_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $totalEarnings = $stmt->fetch()['total_earnings'];

    Response::success([
        'total_orders' => (int) $totalOrders,
        'completed_orders' => (int) $completedOrders,
        'total_earnings' => (float) $totalEarnings
    ], 'Farmer stats retrieved');

} catch (\PDOException $e) {
    error_log("Farmer stats error: " . $e->getMessage());
    Response::serverError('Failed to retrieve farmer stats');
}
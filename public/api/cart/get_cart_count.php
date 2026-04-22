<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$userId = $auth->getUserId();

if (!$userId) {
    Response::success(['count' => 0], 'Cart count retrieved');
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total = (int) $stmt->fetch()['total'];
    Response::success(['count' => $total], 'Cart count retrieved');
} catch (\PDOException $e) {
    error_log("Cart count error: " . $e->getMessage());
    Response::serverError('Failed to get cart count');
}
<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;

// Auth check
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    Response::unauthorized('Only farmers allowed');
}

try {
    $db = Database::getInstance();

    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            p.quantity,
            p.unit,
            p.created_at,
            pi.image_url
        FROM products p
        LEFT JOIN product_images pi 
            ON p.id = pi.product_id AND pi.is_primary = true
        WHERE p.farmer_id = ? 
        AND p.status = true
        ORDER BY p.created_at DESC
    ");

    $stmt->execute([$_SESSION['user_id']]);
    $products = $stmt->fetchAll();

    Response::success($products, 'My products retrieved');

} catch (Exception $e) {
    Response::serverError('Failed to load products');
}
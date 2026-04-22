<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * TRUSTLINK - Get Orders API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Retrieves orders for the authenticated user
 * Features:
 * - Buyers see their orders
 * - Farmers see orders they received
 * - Admins see all orders
 * - Filtering by status
 * - Pagination
 * 
 * HTTP Method: GET
 * Endpoint: /api/orders/get_orders.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Query Parameters:
 * - role: buyer, farmer, admin (auto-detected if not provided)
 * - status: pending, accepted, completed, cancelled
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 10)
 * 
 * Response:
 * - 200: Orders list with pagination
 * - 401: Unauthorized
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\UserRole;
use TrustLink\Config\OrderStatus;
use TrustLink\Config\Pagination;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// ============================================================================
// PARSE QUERY PARAMETERS
// ============================================================================

$page = Pagination::validatePage($_GET['page'] ?? 1);
$perPage = Pagination::validatePerPage($_GET['per_page'] ?? 10);
$offset = Pagination::getOffset($page, $perPage);

$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$roleFilter = isset($_GET['role']) ? $_GET['role'] : null;

// Validate status filter
if ($statusFilter && !in_array($statusFilter, OrderStatus::all())) {
    Response::badRequest('Invalid status filter');
}

// Determine role based on user role and filter
if ($roleFilter === 'admin' && $user['role'] !== UserRole::ADMIN) {
    Response::forbidden('Only admins can view admin orders');
}

$isAdmin = ($user['role'] === UserRole::ADMIN);
$isFarmer = ($user['role'] === UserRole::FARMER);
$isBuyer = ($user['role'] === UserRole::BUYER);

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // BUILD WHERE CLAUSE
    // ============================================================================
    
    $whereConditions = [];
    $params = [];
    
    if ($roleFilter === 'admin' || ($roleFilter === null && $isAdmin)) {
        // Admin sees all orders
        // No user filter
    } elseif ($roleFilter === 'farmer' || ($roleFilter === null && $isFarmer)) {
        $whereConditions[] = "o.farmer_id = ?";
        $params[] = $user['id'];
    } elseif ($roleFilter === 'buyer' || ($roleFilter === null && $isBuyer)) {
        $whereConditions[] = "o.buyer_id = ?";
        $params[] = $user['id'];
    } else {
        $whereConditions[] = "(o.buyer_id = ? OR o.farmer_id = ?)";
        $params[] = $user['id'];
        $params[] = $user['id'];
    }
    
    if ($statusFilter) {
        $whereConditions[] = "o.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);
    
    // ============================================================================
    // GET TOTAL COUNT
    // ============================================================================
    
    $countSql = "
        SELECT COUNT(*) as total
        FROM orders o
        {$whereClause}
    ";
    
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // ============================================================================
    // GET ORDERS
    // ============================================================================
    
    $sql = "
        SELECT 
            o.id, o.order_code, o.total, o.location, o.status,
            o.payment_method, o.payment_status, o.delivery_fee,
            o.created_at, o.completed_at,
            buyer.id as buyer_id, buyer.name as buyer_name, buyer.phone as buyer_phone,
            farmer.id as farmer_id, farmer.name as farmer_name, farmer.phone as farmer_phone,
            farmer.trust_score as farmer_trust_score,
            farmer.verification_tier as farmer_verification_tier,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        JOIN users buyer ON o.buyer_id = buyer.id
        JOIN users farmer ON o.farmer_id = farmer.id
        {$whereClause}
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $queryParams = array_merge($params, [$perPage, $offset]);
    $stmt = $db->prepare($sql);
    $stmt->execute($queryParams);
    $orders = $stmt->fetchAll();
    
    // ============================================================================
    // PROCESS ORDERS
    // ============================================================================
    
    $processedOrders = [];
    foreach ($orders as $order) {
        $processedOrders[] = [
            'id' => $order['id'],
            'order_code' => $order['order_code'],
            'total' => (float) $order['total'],
            'delivery_fee' => (float) $order['delivery_fee'],
            'subtotal' => (float) ($order['total'] - $order['delivery_fee']),
            'location' => $order['location'],
            'status' => $order['status'],
            'status_display' => OrderStatus::displayName($order['status']),
            'status_badge' => OrderStatus::badgeClass($order['status']),
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'item_count' => (int) $order['item_count'],
            'buyer' => [
                'id' => $order['buyer_id'],
                'name' => $order['buyer_name'],
                'phone' => $order['buyer_phone']
            ],
            'farmer' => [
                'id' => $order['farmer_id'],
                'name' => $order['farmer_name'],
                'phone' => $order['farmer_phone'],
                'trust_score' => (float) $order['farmer_trust_score'],
                'verification_tier' => $order['farmer_verification_tier']
            ],
            'created_at' => $order['created_at'],
            'completed_at' => $order['completed_at']
        ];
    }
    
    // ============================================================================
    // PAGINATION METADATA
    // ============================================================================
    
    $totalPages = ceil($total / $perPage);
    
    $meta = [
        'pagination' => [
            'total' => (int) $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_previous' => $page > 1
        ],
        'filters' => [
            'status' => $statusFilter,
            'role' => $roleFilter
        ]
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($processedOrders, 'Orders retrieved successfully', 200, $meta);
    
} catch (\PDOException $e) {
    error_log("Get orders error: " . $e->getMessage());
    Response::serverError('Failed to retrieve orders', false, $e);
}
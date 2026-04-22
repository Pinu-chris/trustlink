<?php
require_once dirname(__DIR__, 2) . '/utils/auth_middleware.php';

// 2. ACTIVATE THE SHIELD (Crucial Step!)
// This one line ensures only Admins can proceed. 
// Everyone else (including Farmers/Buyers) gets kicked out immediately.
$adminData = require_admin(); // ✅ This looks in the Global space where we put it


/**
 * TRUSTLINK - Get Reports API (Admin Only)
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Provides platform analytics and reports
 * Features:
 * - User statistics
 * - Order statistics
 * - Revenue overview
 * - Product statistics
 * - Activity overview
 * 
 * HTTP Method: GET
 * Endpoint: /api/admin/get_reports.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Query Parameters:
 * - period: today, week, month, year, all (default: month)
 * 
 * Response:
 * - 200: Report data
 * - 401: Unauthorized
 * - 403: Not admin
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\OrderStatus;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require admin role
$auth = new AuthMiddleware();
$auth->requireAdmin();

// ============================================================================
// PARSE QUERY PARAMETERS
// ============================================================================

$period = $_GET['period'] ?? 'month';
$allowedPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $allowedPeriods)) {
    $period = 'month';
}

// Calculate date range based on period
$dateCondition = '';
$dateParams = [];

switch ($period) {
    case 'today':
        $dateCondition = "AND created_at >= CURRENT_DATE";
        break;
    case 'week':
        $dateCondition = "AND created_at >= CURRENT_DATE - INTERVAL '7 days'";
        break;
    case 'month':
        $dateCondition = "AND created_at >= CURRENT_DATE - INTERVAL '30 days'";
        break;
    case 'year':
        $dateCondition = "AND created_at >= CURRENT_DATE - INTERVAL '365 days'";
        break;
    case 'all':
    default:
        $dateCondition = "";
        break;
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // USER STATISTICS
    // ============================================================================
    
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'buyer' THEN 1 END) as total_buyers,
            COUNT(CASE WHEN role = 'farmer' THEN 1 END) as total_farmers,
            COUNT(CASE WHEN role = 'service_provider' THEN 1 END) as total_service_providers,
            COUNT(CASE WHEN status = true THEN 1 END) as active_users,
            COUNT(CASE WHEN status = false THEN 1 END) as suspended_users,
            COUNT(CASE WHEN id_verified = true THEN 1 END) as verified_users
        FROM users
    ");
    $userStats = $stmt->fetch();
    
    // New users in period
    $stmt = $db->prepare("
        SELECT COUNT(*) as new_users
        FROM users
        WHERE 1=1 {$dateCondition}
    ");
    $stmt->execute($dateParams);
    $newUsers = $stmt->fetch()['new_users'];
    
    // ============================================================================
    // ORDER STATISTICS
    // ============================================================================
    
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
            COALESCE(SUM(total), 0) as total_revenue,
            COALESCE(AVG(total), 0) as average_order_value
        FROM orders
    ");
    $orderStats = $stmt->fetch();
    
    // Orders in period
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as orders_in_period,
            COALESCE(SUM(total), 0) as revenue_in_period
        FROM orders
        WHERE 1=1 {$dateCondition}
    ");
    $stmt->execute($dateParams);
    $periodOrders = $stmt->fetch();
    
    // ============================================================================
    // PRODUCT STATISTICS
    // ============================================================================
    
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_products,
            COUNT(CASE WHEN status = true THEN 1 END) as active_products,
            COUNT(CASE WHEN status = false THEN 1 END) as inactive_products,
            COALESCE(AVG(price), 0) as average_price,
            COALESCE(SUM(quantity), 0) as total_stock
        FROM products
    ");
    $productStats = $stmt->fetch();
    
    // Top categories
    $stmt = $db->query("
        SELECT 
            category,
            COUNT(*) as product_count
        FROM products
        WHERE status = true
        GROUP BY category
        ORDER BY product_count DESC
        LIMIT 5
    ");
    $topCategories = $stmt->fetchAll();
    
    // ============================================================================
    // REVIEW STATISTICS
    // ============================================================================
    
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_reviews,
            COALESCE(AVG(rating), 0) as average_rating,
            COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
            COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
            COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
            COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
            COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
        FROM reviews
    ");
    $reviewStats = $stmt->fetch();
    
    // ============================================================================
    // ACTIVITY STATISTICS (last 7 days)
    // ============================================================================
    
    $stmt = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as activity_count
        FROM activity_logs
        WHERE created_at >= CURRENT_DATE - INTERVAL '7 days'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $activityTimeline = $stmt->fetchAll();
    
    // ============================================================================
    // TOP PERFORMING FARMERS
    // ============================================================================
    
    $stmt = $db->query("
        SELECT 
            u.id, u.name, u.trust_score,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.total), 0) as total_earnings,
            COUNT(r.id) as total_reviews,
            COALESCE(AVG(r.rating), 0) as avg_rating
        FROM users u
        LEFT JOIN orders o ON u.id = o.farmer_id AND o.status = 'completed'
        LEFT JOIN reviews r ON u.id = r.seller_id
        WHERE u.role = 'farmer'
        GROUP BY u.id, u.name, u.trust_score
        ORDER BY total_earnings DESC
        LIMIT 10
    ");
    $topFarmers = $stmt->fetchAll();
    

    // Daily revenue (last 30 days)
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COALESCE(SUM(total), 0) as revenue
    FROM orders
    WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$dailyRevenue = $stmt->fetchAll();

// Daily new users (last 30 days)
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM users
    WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$dailyNewUsers = $stmt->fetchAll();

// Daily new products (last 30 days)
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM products
    WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$dailyNewProducts = $stmt->fetchAll();
    // ============================================================================
    // BUILD REPORT
    // ============================================================================
    
    $report = [
        'period' => $period,
        'generated_at' => date('Y-m-d H:i:s'),
        'users' => [
            'total' => (int) $userStats['total_users'],
            'buyers' => (int) $userStats['total_buyers'],
            'farmers' => (int) $userStats['total_farmers'],
            'service_providers' => (int) $userStats['total_service_providers'],
            'active' => (int) $userStats['active_users'],
            'suspended' => (int) $userStats['suspended_users'],
            'verified' => (int) $userStats['verified_users'],
            'new_in_period' => (int) $newUsers
        ],
        'orders' => [
            'total' => (int) $orderStats['total_orders'],
            'pending' => (int) $orderStats['pending_orders'],
            'accepted' => (int) $orderStats['accepted_orders'],
            'completed' => (int) $orderStats['completed_orders'],
            'cancelled' => (int) $orderStats['cancelled_orders'],
            'total_revenue' => (float) $orderStats['total_revenue'],
            'average_order_value' => (float) $orderStats['average_order_value'],
            'orders_in_period' => (int) $periodOrders['orders_in_period'],
            'revenue_in_period' => (float) $periodOrders['revenue_in_period']
        ],
        'products' => [
            'total' => (int) $productStats['total_products'],
            'active' => (int) $productStats['active_products'],
            'inactive' => (int) $productStats['inactive_products'],
            'average_price' => (float) $productStats['average_price'],
            'total_stock' => (int) $productStats['total_stock'],
            'top_categories' => $topCategories
        ],
        'reviews' => [
            'total' => (int) $reviewStats['total_reviews'],
            'average_rating' => (float) $reviewStats['average_rating'],
            'distribution' => [
                5 => (int) $reviewStats['five_star'],
                4 => (int) $reviewStats['four_star'],
                3 => (int) $reviewStats['three_star'],
                2 => (int) $reviewStats['two_star'],
                1 => (int) $reviewStats['one_star']
            ]
        ],
        'activity_timeline' => $activityTimeline,
        'top_farmers' => array_map(function($farmer) {
            return [
                'id' => $farmer['id'],
                'name' => $farmer['name'],
                'trust_score' => (float) $farmer['trust_score'],
                'total_orders' => (int) $farmer['total_orders'],
                'total_earnings' => (float) $farmer['total_earnings'],
                'total_reviews' => (int) $farmer['total_reviews'],
                'avg_rating' => (float) $farmer['avg_rating']
            ];
        }, $topFarmers)
    ];

                    $report['daily_revenue'] = $dailyRevenue;
                    $report['daily_new_users'] = $dailyNewUsers;
                    $report['daily_new_products'] = $dailyNewProducts;
                        
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($report, 'Report generated successfully');
    
} catch (\PDOException $e) {
    error_log("Get reports error: " . $e->getMessage());
    Response::serverError('Failed to generate report', false, $e);
}
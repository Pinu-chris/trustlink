<?php
require_once dirname(__DIR__, 2) . '/utils/auth_middleware.php';

// 2. ACTIVATE THE SHIELD (Crucial Step!)
// This one line ensures only Admins can proceed. 
// Everyone else (including Farmers/Buyers) gets kicked out immediately.
$adminData = require_admin(); // ✅ This looks in the Global space where we put it





/**
 * TRUSTLINK - Get Users API (Admin Only)
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Retrieves all users with filtering and pagination
 * Features:
 * - Role filtering
 * - Status filtering (active/suspended)
 * - Search by name/phone
 * - Pagination
 * - Admin-only access
 * 
 * HTTP Method: GET
 * Endpoint: /api/admin/get_users.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Query Parameters:
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 20)
 * - role: buyer, farmer, service_provider, admin
 * - status: active, suspended
 * - search: Search term for name or phone
 * 
 * Response:
 * - 200: Users list with pagination
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
use TrustLink\Config\UserRole;
use TrustLink\Config\VerificationTier;
use TrustLink\Config\Pagination;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require admin role
$auth = new AuthMiddleware();
$auth->requireAdmin();

// ============================================================================
// PARSE QUERY PARAMETERS
// ============================================================================

$page = Pagination::validatePage($_GET['page'] ?? 1);
$perPage = Pagination::validatePerPage($_GET['per_page'] ?? 20);
$offset = Pagination::getOffset($page, $perPage);

$role = isset($_GET['role']) ? $_GET['role'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

// Validate role filter
$allowedRoles = [UserRole::BUYER, UserRole::FARMER, UserRole::SERVICE_PROVIDER, UserRole::ADMIN];
if ($role && !in_array($role, $allowedRoles)) {
    Response::badRequest('Invalid role filter');
}

// Validate status filter
$allowedStatuses = ['active', 'suspended'];
if ($status && !in_array($status, $allowedStatuses)) {
    Response::badRequest('Invalid status filter');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // BUILD WHERE CLAUSE
    // ============================================================================
    
    $whereConditions = [];
    $params = [];
    
    if ($role) {
        $whereConditions[] = "role = ?";
        $params[] = $role;
    }
    
    if ($status === 'active') {
        $whereConditions[] = "status = true";
    } elseif ($status === 'suspended') {
        $whereConditions[] = "status = false";
    }
    
    if ($search) {
        $whereConditions[] = "(name ILIKE ? OR phone ILIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $whereClause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);
    
    // ============================================================================
    // GET TOTAL COUNT
    // ============================================================================
    
    $countSql = "SELECT COUNT(*) as total FROM users {$whereClause}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // ============================================================================
    // GET USERS WITH PAGINATION
    // ============================================================================
    
    $sql = "
        SELECT 
            id, name, phone, email, role, trust_score, verification_tier,
            county, subcounty, ward, profile_photo, status, id_verified,
            created_at, last_login_at
        FROM users 
        {$whereClause}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $queryParams = array_merge($params, [$perPage, $offset]);
    $stmt = $db->prepare($sql);
    $stmt->execute($queryParams);
    $users = $stmt->fetchAll();
    
    // ============================================================================
    // PROCESS USERS
    // ============================================================================
    
    $processedUsers = [];
    foreach ($users as $user) {
        // Get statistics based on role
        $stats = [];
        
        if ($user['role'] === UserRole::FARMER) {
            // Farmer statistics
            $stmtStats = $db->prepare("
                SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
                    COALESCE(SUM(o.total), 0) as total_earnings,
                    COUNT(DISTINCT p.id) as active_products
                FROM users u
                LEFT JOIN orders o ON u.id = o.farmer_id
                LEFT JOIN products p ON u.id = p.farmer_id AND p.status = true
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmtStats->execute([$user['id']]);
            $stats = $stmtStats->fetch();
        } elseif ($user['role'] === UserRole::BUYER) {
            // Buyer statistics
            $stmtStats = $db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total), 0) as total_spent
                FROM orders 
                WHERE buyer_id = ?
            ");
            $stmtStats->execute([$user['id']]);
            $stats = $stmtStats->fetch();
        }
        
        $processedUsers[] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'role' => $user['role'],
            'role_display' => UserRole::displayName($user['role']),
            'trust_score' => (float) $user['trust_score'],
            'verification_tier' => $user['verification_tier'],
            'verification_display' => VerificationTier::displayName($user['verification_tier']),
            'location' => [
                'county' => $user['county'],
                'subcounty' => $user['subcounty'],
                'ward' => $user['ward']
            ],
            'profile_photo' => $user['profile_photo'] 
                ? getenv('APP_URL') . '/assets/images/uploads/profile/' . $user['profile_photo']
                : null,
            'status' => (bool) $user['status'],
            'id_verified' => (bool) $user['id_verified'],
            'joined_at' => $user['created_at'],
            'last_login' => $user['last_login_at'],
            'statistics' => $stats ?: []
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
            'role' => $role,
            'status' => $status,
            'search' => $search
        ]
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($processedUsers, 'Users retrieved successfully', 200, $meta);
    
} catch (\PDOException $e) {
    error_log("Get users error: " . $e->getMessage());
    Response::serverError('Failed to retrieve users', false, $e);
}
<?php
 

// Disable error display in output (log instead)
 
/**
 * TRUSTLINK - Get User Profile API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Retrieves current authenticated user's profile data
 * Features:
 * - Returns full profile with trust score and verification tier
 * - Includes statistics (total orders, total reviews, etc.)
 * - Session validation required
 * 
 * HTTP Method: GET
 * Endpoint: /api/users/get_profile.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Response:
 * - 200: User profile data
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
use TrustLink\Config\TrustBadge;
use TrustLink\Config\VerificationTier;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // FETCH USER PROFILE DATA
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            id, name, phone, email, role, trust_score, verification_tier,
            county, subcounty, ward, profile_photo, status, id_verified,
            created_at, last_login_at
        FROM users 
        WHERE id = ? AND status = true
    ");
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        Response::notFound('User profile');
    }
    
    // ============================================================================
    // FETCH USER STATISTICS
    // ============================================================================
    
    $stats = [];
    
    // Get order statistics based on role
    if ($profile['role'] === UserRole::BUYER) {
        // Buyer statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_orders,
                COALESCE(SUM(total), 0) as total_spent
            FROM orders 
            WHERE buyer_id = ?
        ");
        $stmt->execute([$profile['id']]);
        $stats = $stmt->fetch();
        
    } elseif ($profile['role'] === UserRole::FARMER) {
        // Farmer statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
                COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.id END) as pending_orders,
                COUNT(DISTINCT CASE WHEN o.status = 'accepted' THEN o.id END) as accepted_orders,
                COALESCE(SUM(o.total), 0) as total_earnings,
                COUNT(DISTINCT p.id) as active_products
            FROM users u
            LEFT JOIN orders o ON u.id = o.farmer_id
            LEFT JOIN products p ON u.id = p.farmer_id AND p.status = true
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$profile['id']]);
        $stats = $stmt->fetch();
        
        // If no orders/products, set defaults
        if (!$stats) {
            $stats = [
                'total_orders' => 0,
                'completed_orders' => 0,
                'pending_orders' => 0,
                'accepted_orders' => 0,
                'total_earnings' => 0,
                'active_products' => 0
            ];
        }
    } elseif ($profile['role'] === UserRole::SERVICE_PROVIDER) {
        // Service provider statistics (future expansion)
        $stats = [
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'rating' => $profile['trust_score']
        ];
    }
    
    // ============================================================================
    // FETCH RECENT REVIEWS (for sellers)
    // ============================================================================
    
    $recentReviews = [];
    if ($profile['role'] === UserRole::FARMER || $profile['role'] === UserRole::SERVICE_PROVIDER) {
        $stmt = $db->prepare("
            SELECT 
                r.id, r.rating, r.comment, r.created_at,
                u.name as buyer_name,
                o.order_code
            FROM reviews r
            JOIN users u ON r.buyer_id = u.id
            JOIN orders o ON r.order_id = o.id
            WHERE r.seller_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$profile['id']]);
        $recentReviews = $stmt->fetchAll();
    }
    
    // ============================================================================
    // GET TRUST BADGE INFO
    // ============================================================================
    
    $trustBadge = TrustBadge::getBadge((float) $profile['trust_score']);
    
    // ============================================================================
    // GET VERIFICATION TIER DISPLAY
    // ============================================================================
    
    $verificationInfo = [
        'tier' => $profile['verification_tier'],
        'display_name' => VerificationTier::displayName($profile['verification_tier']),
        'badge_class' => VerificationTier::badgeClass($profile['verification_tier']),
        'description' => VerificationTier::description($profile['verification_tier'])
    ];
    
    // ============================================================================
    // BUILD PROFILE RESPONSE
    // ============================================================================
    
    $profileData = [
        'id' => $profile['id'],
        'name' => $profile['name'],
        'phone' => $profile['phone'],
        'email' => $profile['email'],
        'role' => $profile['role'],
        'role_display' => UserRole::displayName($profile['role']),
        'role_icon' => UserRole::icon($profile['role']),
        'trust_score' => (float) $profile['trust_score'],
        'trust_badge' => $trustBadge,
        'verification' => $verificationInfo,
        'id_verified' => (bool) $profile['id_verified'],
        'location' => [
            'county' => $profile['county'],
            'subcounty' => $profile['subcounty'],
            'ward' => $profile['ward']
        ],
        'profile_photo' => $profile['profile_photo'] 
            ? getenv('APP_URL') . '/assets/images/uploads/profile/' . $profile['profile_photo']
            : null,
        'joined_at' => $profile['created_at'],
        'last_login' => $profile['last_login_at'],
        'statistics' => $stats,
        'recent_reviews' => $recentReviews
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($profileData, 'Profile retrieved successfully');
    
} catch (\PDOException $e) {
    error_log("Get profile error: " . $e->getMessage());
    Response::serverError('Failed to retrieve profile', false, $e);
}
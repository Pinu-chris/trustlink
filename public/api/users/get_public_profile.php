<?php
/**
 * TRUSTLINK - Get Public User Profile API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Retrieves public profile data for any user (no auth required for viewing)
 * Features:
 * - Returns public-facing profile (trust score, reviews, etc.)
 * - No sensitive data (phone, email hidden)
 * - Includes seller statistics
 * 
 * HTTP Method: GET
 * Endpoint: /api/users/get_public_profile.php?user_id=123
 * 
 * Query Parameters:
 * - user_id: ID of user to fetch
 * 
 * Response:
 * - 200: Public profile data
 * - 404: User not found
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

// Get user ID from query string
$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($userId <= 0) {
    Response::badRequest('User ID is required');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // FETCH USER PROFILE DATA (public only)
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            id, name, role, trust_score, verification_tier,
            county, subcounty, ward, profile_photo, id_verified,
            created_at
        FROM users 
        WHERE id = ? AND status = true
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        Response::notFound('User');
    }
    
    // ============================================================================
    // FETCH USER STATISTICS (public view)
    // ============================================================================
    
    $stats = [];
    $recentReviews = [];
    
    if ($profile['role'] === UserRole::FARMER || $profile['role'] === UserRole::SERVICE_PROVIDER) {
        // Seller statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(DISTINCT r.id) as total_reviews,
                COUNT(DISTINCT p.id) as active_products
            FROM users u
            LEFT JOIN orders o ON u.id = o.farmer_id
            LEFT JOIN reviews r ON u.id = r.seller_id
            LEFT JOIN products p ON u.id = p.farmer_id AND p.status = true
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        if (!$stats) {
            $stats = [
                'total_orders' => 0,
                'completed_orders' => 0,
                'avg_rating' => (float) $profile['trust_score'],
                'total_reviews' => 0,
                'active_products' => 0
            ];
        }
        
        // Fetch recent reviews
        $stmt = $db->prepare("
            SELECT 
                r.id, r.rating, r.comment, r.created_at,
                u.name as buyer_name
            FROM reviews r
            JOIN users u ON r.buyer_id = u.id
            WHERE r.seller_id = ?
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $recentReviews = $stmt->fetchAll();
    }
    
    // ============================================================================
    // GET TRUST BADGE INFO
    // ============================================================================
    
    $trustBadge = TrustBadge::getBadge((float) $profile['trust_score']);
    
    // ============================================================================
    // BUILD PUBLIC PROFILE RESPONSE
    // ============================================================================
    
    $profileData = [
        'id' => $profile['id'],
        'name' => $profile['name'],
        'role' => $profile['role'],
        'role_display' => UserRole::displayName($profile['role']),
        'role_icon' => UserRole::icon($profile['role']),
        'trust_score' => (float) $profile['trust_score'],
        'trust_badge' => $trustBadge,
        'verification_tier' => [
            'tier' => $profile['verification_tier'],
            'display_name' => VerificationTier::displayName($profile['verification_tier']),
            'badge_class' => VerificationTier::badgeClass($profile['verification_tier'])
        ],
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
        'statistics' => $stats,
        'recent_reviews' => $recentReviews
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($profileData, 'Profile retrieved successfully');
    
} catch (\PDOException $e) {
    error_log("Get public profile error: " . $e->getMessage());
    Response::serverError('Failed to retrieve profile', false, $e);
}
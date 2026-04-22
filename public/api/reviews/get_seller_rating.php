<?php
/**
 * TRUSTLINK - Get Seller Rating API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Quick rating summary for a seller (lightweight)
 * Features:
 * - Fast response for displaying trust scores
 * - No pagination, just summary stats
 * - Public access
 * 
 * HTTP Method: GET
 * Endpoint: /api/reviews/get_seller_rating.php?seller_id=123
 * 
 * Query Parameters:
 * - seller_id: ID of seller to get rating for
 * 
 * Response:
 * - 200: Rating summary
 * - 404: Seller not found
 */

// Enable CORS and set headers
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

// Load required files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/response.php';

use TrustLink\Config\Database;
use TrustLink\Config\TrustBadge;
use TrustLink\Config\VerificationTier;
use TrustLink\Utils\Response;

// Get seller ID from query string
$sellerId = isset($_GET['seller_id']) ? (int) $_GET['seller_id'] : 0;

if ($sellerId <= 0) {
    Response::badRequest('Seller ID is required');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // GET SELLER BASIC INFO AND TRUST SCORE
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            id, name, trust_score, verification_tier, profile_photo,
            (SELECT COUNT(*) FROM reviews WHERE seller_id = ?) as total_reviews,
            (SELECT ROUND(AVG(rating)::numeric, 1) FROM reviews WHERE seller_id = ?) as average_rating
        FROM users 
        WHERE id = ? AND status = true AND role IN ('farmer', 'service_provider')
    ");
    $stmt->execute([$sellerId, $sellerId, $sellerId]);
    $seller = $stmt->fetch();
    
    if (!$seller) {
        Response::notFound('Seller');
    }
    
    // ============================================================================
    // GET TRUST BADGE
    // ============================================================================
    
    $trustScore = (float) $seller['trust_score'];
    $trustBadge = TrustBadge::getBadge($trustScore);
    
    // ============================================================================
    // BUILD RESPONSE
    // ============================================================================
    
    $response = [
        'seller' => [
            'id' => $seller['id'],
            'name' => $seller['name'],
            'trust_score' => $trustScore,
            'trust_badge' => $trustBadge,
            'verification_tier' => $seller['verification_tier'],
            'verification_display' => VerificationTier::displayName($seller['verification_tier']),
            'profile_photo' => $seller['profile_photo'] 
                ? getenv('APP_URL') . '/assets/images/uploads/profile/' . $seller['profile_photo']
                : null
        ],
        'rating_summary' => [
            'average' => (float) ($seller['average_rating'] ?: 0),
            'total' => (int) $seller['total_reviews'],
            'stars_display' => TrustBadge::getStars($trustScore),
            'star_count' => TrustBadge::getStarCount($trustScore)
        ]
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($response, 'Seller rating retrieved successfully');
    
} catch (\PDOException $e) {
    error_log("Get seller rating error: " . $e->getMessage());
    Response::serverError('Failed to retrieve seller rating', false, $e);
}
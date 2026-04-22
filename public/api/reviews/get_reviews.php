<?php
/**
 * TRUSTLINK - Get Reviews API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Retrieves reviews for a specific seller
 * Features:
 * - Pagination support
 * - Rating summary statistics
 * - Sorted by newest first
 * - Public access (no auth required for viewing)
 * 
 * HTTP Method: GET
 * Endpoint: /api/reviews/get_reviews.php?seller_id=123
 * 
 * Query Parameters:
 * - seller_id: ID of seller to get reviews for
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 10)
 * 
 * Response:
 * - 200: Reviews list with pagination and summary
 * - 404: Seller not found
 */

// Enable CORS and set headers
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

// Load required files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\TrustBadge;
use TrustLink\Config\Pagination;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Get seller ID from query string
$sellerId = isset($_GET['seller_id']) ? (int) $_GET['seller_id'] : 0;

if ($sellerId <= 0) {
    Response::badRequest('Seller ID is required');
}

// Pagination parameters
$page = Pagination::validatePage($_GET['page'] ?? 1);
$perPage = Pagination::validatePerPage($_GET['per_page'] ?? 10);
$offset = Pagination::getOffset($page, $perPage);

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // VERIFY SELLER EXISTS AND IS ACTIVE
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT id, name, trust_score, verification_tier, profile_photo, created_at
        FROM users 
        WHERE id = ? AND status = true AND role IN ('farmer', 'service_provider')
    ");
    $stmt->execute([$sellerId]);
    $seller = $stmt->fetch();
    
    if (!$seller) {
        Response::notFound('Seller');
    }
    
    // ============================================================================
    // GET REVIEW SUMMARY STATISTICS
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            ROUND(AVG(rating)::numeric, 1) as average_rating,
            COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
            COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
            COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
            COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
            COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
        FROM reviews 
        WHERE seller_id = ?
    ");
    $stmt->execute([$sellerId]);
    $summary = $stmt->fetch();
    
    // Calculate percentages
    $total = (int) $summary['total_reviews'];
    $summary['five_star_percent'] = $total > 0 ? round(($summary['five_star'] / $total) * 100) : 0;
    $summary['four_star_percent'] = $total > 0 ? round(($summary['four_star'] / $total) * 100) : 0;
    $summary['three_star_percent'] = $total > 0 ? round(($summary['three_star'] / $total) * 100) : 0;
    $summary['two_star_percent'] = $total > 0 ? round(($summary['two_star'] / $total) * 100) : 0;
    $summary['one_star_percent'] = $total > 0 ? round(($summary['one_star'] / $total) * 100) : 0;
    
    // ============================================================================
    // GET REVIEWS WITH PAGINATION
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            r.id, r.rating, r.comment, r.created_at,
            u.id as buyer_id, u.name as buyer_name, u.profile_photo as buyer_photo,
            o.order_code
        FROM reviews r
        JOIN users u ON r.buyer_id = u.id
        JOIN orders o ON r.order_id = o.id
        WHERE r.seller_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$sellerId, $perPage, $offset]);
    $reviews = $stmt->fetchAll();
    
    // ============================================================================
    // PROCESS REVIEWS
    // ============================================================================
    
    $processedReviews = [];
    foreach ($reviews as $review) {
        $processedReviews[] = [
            'id' => $review['id'],
            'rating' => (int) $review['rating'],
            'comment' => $review['comment'],
            'order_code' => $review['order_code'],
            'buyer' => [
                'id' => $review['buyer_id'],
                'name' => $review['buyer_name'],
                'profile_photo' => $review['buyer_photo'] 
                    ? getenv('APP_URL') . '/assets/images/uploads/profile/' . $review['buyer_photo']
                    : null
            ],
            'created_at' => $review['created_at'],
            'time_ago' => timeAgo($review['created_at'])
        ];
    }
    
    // ============================================================================
    // SELLER INFORMATION
    // ============================================================================
    
    $trustBadge = TrustBadge::getBadge((float) $seller['trust_score']);
    
    $sellerInfo = [
        'id' => $seller['id'],
        'name' => $seller['name'],
        'trust_score' => (float) $seller['trust_score'],
        'trust_badge' => $trustBadge,
        'verification_tier' => $seller['verification_tier'],
        'profile_photo' => $seller['profile_photo'] 
            ? getenv('APP_URL') . '/assets/images/uploads/profile/' . $seller['profile_photo']
            : null,
        'joined_at' => $seller['created_at'],
        'total_reviews' => (int) $summary['total_reviews'],
        'average_rating' => (float) $summary['average_rating']
    ];
    
    // ============================================================================
    // PAGINATION METADATA
    // ============================================================================
    
    $totalPages = ceil($summary['total_reviews'] / $perPage);
    
    $meta = [
        'pagination' => [
            'total' => (int) $summary['total_reviews'],
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_previous' => $page > 1
        ],
        'rating_summary' => [
            'average' => (float) $summary['average_rating'],
            'total' => (int) $summary['total_reviews'],
            'distribution' => [
                5 => ['count' => (int) $summary['five_star'], 'percent' => $summary['five_star_percent']],
                4 => ['count' => (int) $summary['four_star'], 'percent' => $summary['four_star_percent']],
                3 => ['count' => (int) $summary['three_star'], 'percent' => $summary['three_star_percent']],
                2 => ['count' => (int) $summary['two_star'], 'percent' => $summary['two_star_percent']],
                1 => ['count' => (int) $summary['one_star'], 'percent' => $summary['one_star_percent']]
            ]
        ]
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success([
        'seller' => $sellerInfo,
        'reviews' => $processedReviews,
        'meta' => $meta
    ], 'Reviews retrieved successfully');
    
} catch (\PDOException $e) {
    error_log("Get reviews error: " . $e->getMessage());
    Response::serverError('Failed to retrieve reviews', false, $e);
}

// ============================================================================
// HELPER FUNCTION: Time ago
// ============================================================================

function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    }
}
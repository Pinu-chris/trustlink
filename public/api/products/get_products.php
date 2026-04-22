<?php
/**
 * TRUSTLINK - Get Products API (Public)
 * Version: 1.1 | Production Ready | April 2026
 * 
 * Description: Retrieves products with filtering, sorting, and pagination
 * Features:
 * - Search by name
 * - Filter by category
 * - Filter by price range
 * - Filter by location
 * - Filter by verification tier
 * - Sort by price, trust score, newest
 * - Pagination
 * - Caching headers
 * 
 * HTTP Method: GET
 * Endpoint: /api/products/get_products.php
 * 
 * Query Parameters:
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 10, max: 100)
 * - search: Search term
 * - category: Product category
 * - min_price: Minimum price
 * - max_price: Maximum price
 * - location: County or ward
 * - verification_tier: basic, trusted, premium
 * - sort: price_asc, price_desc, trust_desc, newest
 * 
 * Response:
 * - 200: Products list with pagination
 */

// Enable CORS and set headers
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';
use TrustLink\Config\Database;
use TrustLink\Config\ProductCategory;
use TrustLink\Config\VerificationTier;
use TrustLink\Config\Pagination;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth (optional - public endpoint)
$auth = new AuthMiddleware();
$currentUserId = $auth->getUserId();

// ============================================================================
// PARSE QUERY PARAMETERS
// ============================================================================

$page = Pagination::validatePage($_GET['page'] ?? 1);
$perPage = Pagination::validatePerPage($_GET['per_page'] ?? 10);
$offset = Pagination::getOffset($page, $perPage);

$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$category = isset($_GET['category']) ? $_GET['category'] : null;
$minPrice = isset($_GET['min_price']) ? (float) $_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float) $_GET['max_price'] : null;
$location = isset($_GET['location']) ? trim($_GET['location']) : null;
$verificationTier = isset($_GET['verification_tier']) ? $_GET['verification_tier'] : null;
$sort = $_GET['sort'] ?? 'newest';

// Validate category
if ($category && !in_array($category, ProductCategory::all())) {
    Response::badRequest('Invalid category');
}

// Validate verification tier
if ($verificationTier && !in_array($verificationTier, VerificationTier::all())) {
    Response::badRequest('Invalid verification tier');
}

// Validate sort option
$allowedSorts = ['price_asc', 'price_desc', 'trust_desc', 'newest'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'newest';
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // BUILD WHERE CLAUSE
    // ============================================================================
    
    $whereConditions = ["p.status = true", "u.status = true"];
    $params = [];
    
    // Search by name (full-text search)
    if ($search) {
        $whereConditions[] = "p.name ILIKE ?";
        $params[] = "%{$search}%";
    }
    
    // Filter by category
    if ($category) {
        $whereConditions[] = "p.category = ?";
        $params[] = $category;
    }
    
    // Filter by price range
    if ($minPrice !== null) {
        $whereConditions[] = "p.price >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice !== null) {
        $whereConditions[] = "p.price <= ?";
        $params[] = $maxPrice;
    }
    
    // Filter by location (county or ward)
    if ($location) {
        $whereConditions[] = "(u.county ILIKE ? OR u.ward ILIKE ?)";
        $params[] = "%{$location}%";
        $params[] = "%{$location}%";
    }
    
    // Filter by verification tier
    if ($verificationTier) {
        $whereConditions[] = "u.verification_tier = ?";
        $params[] = $verificationTier;
    }
    
    // Exclude current user's own products (optional)
            // Check if we are filtering by a specific farmer (e.g., for dashboard)
        $farmerId = isset($_GET['farmer_id']) ? (int) $_GET['farmer_id'] : null;

        if ($farmerId) {
            // Explicit request: show this farmer's products
            $whereConditions[] = "p.farmer_id = ?";
            $params[] = $farmerId;
        } 
        if ($farmerId) {
            $whereConditions[] = "p.farmer_id = ?";
            $params[] = $farmerId;
        } else if ($currentUserId) {
            // Allow user to see everything INCLUDING their own
            // (no exclusion)
        }
        // ❌ REMOVE THIS PART COMPLETELY
        // else if ($currentUserId) {
        //     $whereConditions[] = "p.farmer_id != ?";
        //     $params[] = $currentUserId;
        // }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    // ============================================================================
    // BUILD ORDER BY CLAUSE
    // ============================================================================
    
    switch ($sort) {
        case 'price_asc':
            $orderBy = "ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $orderBy = "ORDER BY p.price DESC";
            break;
        case 'trust_desc':
            $orderBy = "ORDER BY u.trust_score DESC NULLS LAST";
            break;
        case 'newest':
        default:
            $orderBy = "ORDER BY p.created_at DESC";
            break;
    }
    
    // ============================================================================
    // GET TOTAL COUNT
    // ============================================================================
    
    $countSql = "
        SELECT COUNT(DISTINCT p.id) as total
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        {$whereClause}
    ";
    
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // ============================================================================
    // GET PRODUCTS WITH PAGINATION
    // ============================================================================
    
            $sql = "
                SELECT 
                    p.id, p.name, p.category, p.description, p.price, p.quantity, p.unit,
                    p.created_at, p.views_count,
                    u.id as farmer_id, u.name as farmer_name, u.trust_score as farmer_trust_score,
                    u.verification_tier as farmer_verification_tier, u.county, u.ward,
                    pi.image_url as primary_image,
                    (
                        SELECT AVG(r.rating)
                        FROM reviews r
                        JOIN orders o ON r.order_id = o.id
                        JOIN order_items oi ON o.id = oi.order_id
                        WHERE oi.product_id = p.id
                    ) AS avg_rating,
                    (
                        SELECT COUNT(r.id)
                        FROM reviews r
                        JOIN orders o ON r.order_id = o.id
                        JOIN order_items oi ON o.id = oi.order_id
                        WHERE oi.product_id = p.id
                    ) AS review_count
                FROM products p
                JOIN users u ON p.farmer_id = u.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                {$whereClause}
                {$orderBy}
                LIMIT ? OFFSET ?
            ";
    
    $queryParams = array_merge($params, [$perPage, $offset]);
    $stmt = $db->prepare($sql);
    $stmt->execute($queryParams);
    $products = $stmt->fetchAll();
    
    // ============================================================================
    // PROCESS PRODUCTS
    // ============================================================================
    
    // Helper: get trust badge array based on trust score (fallback if TrustBadge class missing)
    if (!class_exists('TrustBadge')) {
        function getTrustBadge($score) {
            if ($score >= 90) {
                return ['level' => 'premium', 'icon' => '🏆', 'color' => 'gold'];
            } elseif ($score >= 70) {
                return ['level' => 'trusted', 'icon' => '✅', 'color' => 'green'];
            } else {
                return ['level' => 'basic', 'icon' => '⭐', 'color' => 'gray'];
            }
        }
    } else {
        function getTrustBadge($score) {
            return TrustBadge::getBadge($score);
        }
    }
    
    // Helper: get unit abbreviation (fallback if UnitType class missing)
    if (!class_exists('UnitType')) {
        function getUnitAbbrev($unit) {
            $map = [
                'kilogram' => 'kg',
                'gram' => 'g',
                'litre' => 'L',
                'millilitre' => 'mL',
                'piece' => 'pc',
                'bunch' => 'bunch',
                'bundle' => 'bundle',
                'bag' => 'bag'
            ];
            return $map[strtolower($unit)] ?? $unit;
        }
    } else {
        function getUnitAbbrev($unit) {
            return UnitType::abbreviation($unit);
        }
    }
    
    $processedProducts = [];
    foreach ($products as $product) {
        // Get trust badge (using fallback if needed)
        $trustBadge = getTrustBadge((float) $product['farmer_trust_score']);
        
        // Get unit abbreviation
        $unitAbbr = getUnitAbbrev($product['unit']);
        
        // Build image URL safely
           // Build image URL safely – clean up APP_URL
        $appUrl = getenv('APP_URL');
        if ($appUrl) {
            // Remove any comment after #
            if (($pos = strpos($appUrl, '#')) !== false) {
                $appUrl = substr($appUrl, 0, $pos);
            }
            // Trim quotes and whitespace
            $appUrl = trim($appUrl, '"\'');
            $appUrl = rtrim($appUrl, '/');
        }
        // Fallback if still empty
        if (empty($appUrl)) {
            $appUrl = 'http://localhost/trustfiles';
        }
        
        $primaryImage = $product['primary_image'] 
            ? $appUrl . '/assets/images/uploads/products/' . $product['primary_image']
            : null;
        
        $processedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'category' => $product['category'],
            'category_display' => ProductCategory::displayName($product['category']),
            'description' => $product['description'],
            'price' => (float) $product['price'],
            'quantity' => (int) $product['quantity'],
            'unit' => $product['unit'],
            'unit_abbr' => $unitAbbr,
            'primary_image' => $primaryImage,
            'farmer' => [
                'id' => $product['farmer_id'],
                'name' => $product['farmer_name'],
                'trust_score' => (float) $product['farmer_trust_score'],
                'trust_badge' => $trustBadge,
                'verification_tier' => $product['farmer_verification_tier'],
                'location' => [
                    'county' => $product['county'],
                    'ward' => $product['ward']
                ]
            ],
                        'created_at' => $product['created_at'],
            'avg_rating' => $product['avg_rating'] ? (float) $product['avg_rating'] : null,
            'review_count' => (int) $product['review_count']
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
            'search' => $search,
            'category' => $category,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'location' => $location,
            'verification_tier' => $verificationTier,
            'sort' => $sort
        ]
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($processedProducts, 'Products retrieved successfully', 200, $meta);
    
} catch (\PDOException $e) {
    error_log("Get products error: " . $e->getMessage());
    error_log("SQL State: " . ($e->errorInfo[0] ?? 'unknown'));
    error_log("Error Code: " . ($e->errorInfo[1] ?? 'unknown'));
    error_log("Message: " . ($e->errorInfo[2] ?? 'unknown'));
    Response::serverError('Failed to retrieve products', false, $e);
}
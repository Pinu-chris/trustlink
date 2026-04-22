<?php
/**
 * TRUSTLINK - Get Single Product API (Public)
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Retrieves detailed information for a single product
 * Features:
 * - Increments view count
 * - Returns farmer details
 * - Returns product images
 * - Returns related products
 * 
 * HTTP Method: GET
 * Endpoint: /api/products/get_single_product.php?id=123
 * 
 * Query Parameters:
 * - id: Product ID
 * 
 * Response:
 * - 200: Product details
 * - 404: Product not found
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
use TrustLink\Config\UnitType;
use TrustLink\Config\TrustBadge;
use TrustLink\Config\VerificationTier;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Get product ID from query string
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productId <= 0) {
    Response::badRequest('Product ID is required');
}

try {
    $db = Database::getInstance();
    
    // ============================================================================
    // FETCH PRODUCT DETAILS
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            p.id, p.name, p.category, p.description, p.price, p.quantity, p.unit,
            p.status, p.views_count, p.created_at, p.updated_at,
            u.id as farmer_id, u.name as farmer_name, u.phone as farmer_phone,
            u.trust_score as farmer_trust_score, u.verification_tier as farmer_verification_tier,
            u.county, u.subcounty, u.ward, u.profile_photo, u.id_verified,
            u.created_at as farmer_joined_at
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        WHERE p.id = ? AND p.status = true AND u.status = true
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        Response::notFound('Product');
    }
    
    // ============================================================================
    // INCREMENT VIEW COUNT (asynchronous - don't fail if error)
    // ============================================================================
    
    try {
        $stmt = $db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?");
        $stmt->execute([$productId]);
    } catch (\Exception $e) {
        error_log("Failed to increment view count: " . $e->getMessage());
    }
    
    // ============================================================================
    // FETCH PRODUCT IMAGES
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT id, image_url, is_primary, display_order
        FROM product_images
        WHERE product_id = ?
        ORDER BY is_primary DESC, display_order ASC
    ");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();
    
    $productImages = [];
    foreach ($images as $img) {
        $productImages[] = [
            'id' => $img['id'],
            'url' => getenv('APP_URL') . '/assets/images/uploads/products/' . $img['image_url'],
            'is_primary' => (bool) $img['is_primary'],
            'order' => $img['display_order']
        ];
    }
    
    // If no images, use default
    if (empty($productImages)) {
        $productImages[] = [
            'id' => null,
            'url' => getenv('APP_URL') . '/assets/images/default/product-placeholder.jpg',
            'is_primary' => true,
            'order' => 0
        ];
    }
    
    // ============================================================================
    // GET TRUST BADGE
    // ============================================================================
    
    $trustBadge = TrustBadge::getBadge((float) $product['farmer_trust_score']);
    
    // ============================================================================
    // FETCH RELATED PRODUCTS (same category, different farmer)
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT 
            p.id, p.name, p.price, p.unit, p.quantity,
            pi.image_url as primary_image,
            u.trust_score as farmer_trust_score
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
        WHERE p.category = ? 
            AND p.id != ? 
            AND p.status = true 
            AND u.status = true
        LIMIT 4
    ");
    $stmt->execute([$product['category'], $productId]);
    $relatedProducts = $stmt->fetchAll();
    
    $related = [];
    foreach ($relatedProducts as $rp) {
        $related[] = [
            'id' => $rp['id'],
            'name' => $rp['name'],
            'price' => (float) $rp['price'],
            'unit' => $rp['unit'],
            'unit_abbr' => UnitType::abbreviation($rp['unit']),
            'primary_image' => $rp['primary_image'] 
                ? getenv('APP_URL') . '/assets/images/uploads/products/' . $rp['primary_image']
                : null,
            'farmer_trust_score' => (float) $rp['farmer_trust_score']
        ];
    }
    
    // ============================================================================
    // BUILD PRODUCT DATA
    // ============================================================================
    
    $productData = [
        'id' => $product['id'],
        'name' => $product['name'],
        'category' => $product['category'],
        'category_display' => ProductCategory::displayName($product['category']),
        'description' => $product['description'],
        'price' => (float) $product['price'],
        'quantity' => (int) $product['quantity'],
        'unit' => $product['unit'],
        'unit_display' => UnitType::displayName($product['unit']),
        'unit_abbr' => UnitType::abbreviation($product['unit']),
        'views_count' => (int) $product['views_count'],
        'images' => $productImages,
        'farmer' => [
            'id' => $product['farmer_id'],
            'name' => $product['farmer_name'],
            'phone' => $product['farmer_phone'],
            'trust_score' => (float) $product['farmer_trust_score'],
            'trust_badge' => $trustBadge,
            'verification_tier' => $product['farmer_verification_tier'],
            'verification_display' => VerificationTier::displayName($product['farmer_verification_tier']),
            'id_verified' => (bool) $product['id_verified'],
            'profile_photo' => $product['profile_photo'] 
                ? getenv('APP_URL') . '/assets/images/uploads/profile/' . $product['profile_photo']
                : null,
            'location' => [
                'county' => $product['county'],
                'subcounty' => $product['subcounty'],
                'ward' => $product['ward']
            ],
            'joined_at' => $product['farmer_joined_at']
        ],
        'related_products' => $related,
        'created_at' => $product['created_at'],
        'updated_at' => $product['updated_at']
    ];
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success($productData, 'Product retrieved successfully');
    
} catch (\PDOException $e) {
    error_log("Get single product error: " . $e->getMessage());
    Response::serverError('Failed to retrieve product', false, $e);
}
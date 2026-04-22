<?php
/**
 * TRUSTLINK - Application Constants
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Centralized constants for the entire application
 * Usage: Include at the beginning of any file that needs constants
 * 
 * Contains:
 * - Role definitions
 * - Status codes
 * - Trust badge mappings
 * - Order statuses
 * - Payment statuses
 * - Verification tiers
 * - Error codes
 * - API response codes
 * - Category mappings
 * - Unit types
 */

namespace TrustLink\Config;

require_once __DIR__ . '/load_env.php'; // Add this line
EnvironmentLoader::load();             // And this
// ============================================================================
// USER ROLES
// ============================================================================

/**
 * User Role Constants
 * Matches user_role ENUM in database
 */
class UserRole
{
    public const BUYER = 'buyer';
    public const FARMER = 'farmer';
    public const SERVICE_PROVIDER = 'service_provider';
    public const ADMIN = 'admin';
    
    /**
     * Get all roles as array
     * @return array
     */
    public static function all()
    {
        return [
            self::BUYER,
            self::FARMER,
            self::SERVICE_PROVIDER,
            self::ADMIN,
        ];
    }
    
    /**
     * Get role display name
     * @param string $role
     * @return string
     */
    public static function displayName($role)
    {
        $names = [
            self::BUYER => 'Buyer',
            self::FARMER => 'Farmer',
            self::SERVICE_PROVIDER => 'Service Provider',
            self::ADMIN => 'Administrator',
        ];
        
        return $names[$role] ?? ucfirst($role);
    }
    
    /**
     * Get role icon
     * @param string $role
     * @return string
     */
    public static function icon($role)
    {
        $icons = [
            self::BUYER => '🛒',
            self::FARMER => '🌾',
            self::SERVICE_PROVIDER => '🔧',
            self::ADMIN => '👑',
        ];
        
        return $icons[$role] ?? '👤';
    }
}

// ============================================================================
// VERIFICATION TIERS
// ============================================================================

/**
 * Verification Tier Constants
 * Matches verification_tier ENUM in database
 */
class VerificationTier
{
    public const BASIC = 'basic';
    public const TRUSTED = 'trusted';
    public const PREMIUM = 'premium';
    
    /**
     * Get all tiers as array
     * @return array
     */
    public static function all()
    {
        return [
            self::BASIC,
            self::TRUSTED,
            self::PREMIUM,
        ];
    }
    
    /**
     * Get tier display name
     * @param string $tier
     * @return string
     */
    public static function displayName($tier)
    {
        $names = [
            self::BASIC => 'Basic Verified',
            self::TRUSTED => 'Trusted Seller',
            self::PREMIUM => 'Premium Verified',
        ];
        
        return $names[$tier] ?? ucfirst($tier);
    }
    
    /**
     * Get tier badge color (CSS class)
     * @param string $tier
     * @return string
     */
    public static function badgeClass($tier)
    {
        $classes = [
            self::BASIC => 'badge-basic',
            self::TRUSTED => 'badge-trusted',
            self::PREMIUM => 'badge-premium',
        ];
        
        return $classes[$tier] ?? 'badge-default';
    }
    
    /**
     * Get tier description
     * @param string $tier
     * @return string
     */
    public static function description($tier)
    {
        $descriptions = [
            self::BASIC => 'ID verified user',
            self::TRUSTED => 'High ratings and proven reliability',
            self::PREMIUM => 'Audited and fully verified',
        ];
        
        return $descriptions[$tier] ?? 'Verified user';
    }
}

// ============================================================================
// TRUST SCORE BADGES
// ============================================================================

/**
 * Trust Score Badge Constants
 * Based on user trust_score (0-5)
 */
class TrustBadge
{
    public const EXCELLENT = ['min' => 4.5, 'max' => 5.0, 'label' => 'Highly Trusted', 'stars' => 5, 'icon' => '⭐⭐⭐⭐⭐'];
    public const GOOD = ['min' => 3.5, 'max' => 4.4, 'label' => 'Verified', 'stars' => 4, 'icon' => '⭐⭐⭐⭐'];
    public const AVERAGE = ['min' => 2.5, 'max' => 3.4, 'label' => 'Standard', 'stars' => 3, 'icon' => '⭐⭐⭐'];
    public const POOR = ['min' => 0, 'max' => 2.4, 'label' => 'Needs Improvement', 'stars' => 2, 'icon' => '⭐⭐'];
    
    /**
     * Get badge info based on trust score
     * @param float $score
     * @return array
     */
    public static function getBadge($score)
    {
        if ($score >= self::EXCELLENT['min']) {
            return self::EXCELLENT;
        } elseif ($score >= self::GOOD['min']) {
            return self::GOOD;
        } elseif ($score >= self::AVERAGE['min']) {
            return self::AVERAGE;
        } else {
            return self::POOR;
        }
    }
    
    /**
     * Get star display
     * @param float $score
     * @return string
     */
    public static function getStars($score)
    {
        $badge = self::getBadge($score);
        return $badge['icon'];
    }
    
    /**
     * Get numeric stars (for CSS)
     * @param float $score
     * @return int
     */
    public static function getStarCount($score)
    {
        $badge = self::getBadge($score);
        return $badge['stars'];
    }
}

// ============================================================================
// ORDER STATUS
// ============================================================================

/**
 * Order Status Constants
 * Matches order_status ENUM in database
 */
class OrderStatus
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    
    /**
     * Get all statuses as array
     * @return array
     */
    public static function all()
    {
        return [
            self::PENDING,
            self::ACCEPTED,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }
    
    /**
     * Get status display name
     * @param string $status
     * @return string
     */
    public static function displayName($status)
    {
        $names = [
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        ];
        
        return $names[$status] ?? ucfirst($status);
    }
    
    /**
     * Get status badge color (CSS class)
     * @param string $status
     * @return string
     */
    public static function badgeClass($status)
    {
        $classes = [
            self::PENDING => 'status-pending',
            self::ACCEPTED => 'status-accepted',
            self::COMPLETED => 'status-completed',
            self::CANCELLED => 'status-cancelled',
        ];
        
        return $classes[$status] ?? 'status-default';
    }
    
    /**
     * Check if status is cancellable
     * @param string $status
     * @return bool
     */
    public static function isCancellable($status)
    {
        return $status === self::PENDING;
    }
    
    /**
     * Check if status is final (cannot change)
     * @param string $status
     * @return bool
     */
    public static function isFinal($status)
    {
        return in_array($status, [self::COMPLETED, self::CANCELLED]);
    }
}

// ============================================================================
// PAYMENT STATUS
// ============================================================================

/**
 * Payment Status Constants
 * Matches payment_status ENUM in database
 */
class PaymentStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    
    /**
     * Get all payment statuses
     * @return array
     */
    public static function all()
    {
        return [
            self::PENDING,
            self::PAID,
            self::FAILED,
        ];
    }
    
    /**
     * Get status display name
     * @param string $status
     * @return string
     */
    public static function displayName($status)
    {
        $names = [
            self::PENDING => 'Pending Payment',
            self::PAID => 'Paid',
            self::FAILED => 'Payment Failed',
        ];
        
        return $names[$status] ?? ucfirst($status);
    }
    
    /**
     * Get status badge color
     * @param string $status
     * @return string
     */
    public static function badgeClass($status)
    {
        $classes = [
            self::PENDING => 'payment-pending',
            self::PAID => 'payment-paid',
            self::FAILED => 'payment-failed',
        ];
        
        return $classes[$status] ?? 'payment-default';
    }
}

// ============================================================================
// NOTIFICATION TYPES
// ============================================================================

/**
 * Notification Type Constants
 * Matches notification_type ENUM in database
 */
class NotificationType
{
    public const ORDER_PLACED = 'order_placed';
    public const ORDER_ACCEPTED = 'order_accepted';
    public const ORDER_COMPLETED = 'order_completed';
    public const ORDER_CANCELLED = 'order_cancelled';
    public const REVIEW_RECEIVED = 'review_received';
    
    /**
     * Get all notification types
     * @return array
     */
    public static function all()
    {
        return [
            self::ORDER_PLACED,
            self::ORDER_ACCEPTED,
            self::ORDER_COMPLETED,
            self::ORDER_CANCELLED,
            self::REVIEW_RECEIVED,
        ];
    }
    
    /**
     * Get notification message template
     * @param string $type
     * @param array $data
     * @return string
     */
    public static function getMessage($type, $data = [])
    {
        $messages = [
            self::ORDER_PLACED => "New order #{order_code} has been placed.",
            self::ORDER_ACCEPTED => "Your order #{order_code} has been accepted.",
            self::ORDER_COMPLETED => "Order #{order_code} has been completed. Please leave a review!",
            self::ORDER_CANCELLED => "Order #{order_code} has been cancelled.",
            self::REVIEW_RECEIVED => "You received a {rating}-star review on order #{order_code}.",
        ];
        
        $message = $messages[$type] ?? "New notification";
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }
        
        return $message;
    }
}

// ============================================================================
// PRODUCT CATEGORIES
// ============================================================================

/**
 * Product Category Constants
 * Matches product_category ENUM in database
 */
class ProductCategory
{
    public const VEGETABLES = 'vegetables';
    public const FRUITS = 'fruits';
    public const DAIRY = 'dairy';
    public const GRAINS = 'grains';
    public const POULTRY = 'poultry';
    public const OTHER = 'other';
    
    /**
     * Get all categories
     * @return array
     */
    public static function all()
    {
        return [
            self::VEGETABLES,
            self::FRUITS,
            self::DAIRY,
            self::GRAINS,
            self::POULTRY,
            self::OTHER,
        ];
    }
    
    /**
     * Get category display name
     * @param string $category
     * @return string
     */
    public static function displayName($category)
    {
        $names = [
            self::VEGETABLES => 'Vegetables',
            self::FRUITS => 'Fruits',
            self::DAIRY => 'Dairy',
            self::GRAINS => 'Grains',
            self::POULTRY => 'Poultry',
            self::OTHER => 'Other',
        ];
        
        return $names[$category] ?? ucfirst($category);
    }
    
    /**
     * Get category icon
     * @param string $category
     * @return string
     */
    public static function icon($category)
    {
        $icons = [
            self::VEGETABLES => '🥬',
            self::FRUITS => '🍎',
            self::DAIRY => '🥛',
            self::GRAINS => '🌾',
            self::POULTRY => '🐔',
            self::OTHER => '📦',
        ];
        
        return $icons[$category] ?? '📦';
    }
}

// ============================================================================
// UNIT TYPES
// ============================================================================

/**
 * Unit Type Constants
 * For product measurements
 */
class UnitType
{
    public const KG = 'kg';
    public const GRAM = 'g';
    public const BUNCH = 'bunch';
    public const PIECE = 'piece';
    public const LITER = 'liter';
    public const DOZEN = 'dozen';
    
    /**
     * Get all unit types
     * @return array
     */
    public static function all()
    {
        return [
            self::KG,
            self::GRAM,
            self::BUNCH,
            self::PIECE,
            self::LITER,
            self::DOZEN,
        ];
    }
    
    /**
     * Get unit display name
     * @param string $unit
     * @return string
     */
    public static function displayName($unit)
    {
        $names = [
            self::KG => 'Kilogram (kg)',
            self::GRAM => 'Gram (g)',
            self::BUNCH => 'Bunch',
            self::PIECE => 'Piece',
            self::LITER => 'Liter',
            self::DOZEN => 'Dozen',
        ];
        
        return $names[$unit] ?? ucfirst($unit);
    }
    
    /**
     * Get unit abbreviation
     * @param string $unit
     * @return string
     */
    public static function abbreviation($unit)
    {
        $abbr = [
            self::KG => 'kg',
            self::GRAM => 'g',
            self::BUNCH => 'bunch',
            self::PIECE => 'pc',
            self::LITER => 'L',
            self::DOZEN => 'doz',
        ];
        
        return $abbr[$unit] ?? $unit;
    }
}

// ============================================================================
// API RESPONSE CODES
// ============================================================================

/**
 * API Response Codes
 * Standard HTTP status codes with custom codes
 */
class ApiResponseCode
{
    // Success
    public const SUCCESS = 200;
    public const CREATED = 201;
    public const ACCEPTED = 202;
    public const NO_CONTENT = 204;
    
    // Client Errors
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const CONFLICT = 409;
    public const UNPROCESSABLE_ENTITY = 422;
    public const TOO_MANY_REQUESTS = 429;
    
    // Server Errors
    public const INTERNAL_SERVER_ERROR = 500;
    public const NOT_IMPLEMENTED = 501;
    public const BAD_GATEWAY = 502;
    public const SERVICE_UNAVAILABLE = 503;
    
    /**
     * Get message for status code
     * @param int $code
     * @return string
     */
    public static function getMessage($code)
    {
        $messages = [
            self::SUCCESS => 'Success',
            self::CREATED => 'Resource created successfully',
            self::ACCEPTED => 'Request accepted',
            self::NO_CONTENT => 'No content',
            self::BAD_REQUEST => 'Bad request',
            self::UNAUTHORIZED => 'Unauthorized access',
            self::FORBIDDEN => 'Access forbidden',
            self::NOT_FOUND => 'Resource not found',
            self::METHOD_NOT_ALLOWED => 'Method not allowed',
            self::CONFLICT => 'Resource conflict',
            self::UNPROCESSABLE_ENTITY => 'Unprocessable entity',
            self::TOO_MANY_REQUESTS => 'Too many requests',
            self::INTERNAL_SERVER_ERROR => 'Internal server error',
            self::NOT_IMPLEMENTED => 'Not implemented',
            self::BAD_GATEWAY => 'Bad gateway',
            self::SERVICE_UNAVAILABLE => 'Service unavailable',
        ];
        
        return $messages[$code] ?? 'Unknown status';
    }
}

// ============================================================================
// PAGINATION DEFAULTS
// ============================================================================

/**
 * Pagination Defaults
 */
class Pagination
{
    public const DEFAULT_PER_PAGE = 10;
    public const MAX_PER_PAGE = 100;
    public const DEFAULT_PAGE = 1;
    
    /**
     * Validate and sanitize per_page value
     * @param int $perPage
     * @return int
     */
    public static function validatePerPage($perPage)
    {
        $perPage = (int) $perPage;
        
        if ($perPage <= 0) {
            return self::DEFAULT_PER_PAGE;
        }
        
        if ($perPage > self::MAX_PER_PAGE) {
            return self::MAX_PER_PAGE;
        }
        
        return $perPage;
    }
    
    /**
     * Validate and sanitize page value
     * @param int $page
     * @return int
     */
    public static function validatePage($page)
    {
        $page = (int) $page;
        
        if ($page <= 0) {
            return self::DEFAULT_PAGE;
        }
        
        return $page;
    }
    
    /**
     * Calculate offset for SQL LIMIT
     * @param int $page
     * @param int $perPage
     * @return int
     */
    public static function getOffset($page, $perPage)
    {
        $page = self::validatePage($page);
        $perPage = self::validatePerPage($perPage);
        
        return ($page - 1) * $perPage;
    }
}

// ============================================================================
// FILE UPLOAD DEFAULTS
// ============================================================================

/**
 * File Upload Defaults
 */
class FileUpload
{
    public const MAX_SIZE = 5242880;  // 5MB
    public const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    
    /**
     * Check if file type is allowed
     * @param string $mimeType
     * @return bool
     */
    public static function isAllowedType($mimeType)
    {
        return in_array($mimeType, self::ALLOWED_TYPES);
    }
    
    /**
     * Check if file extension is allowed
     * @param string $extension
     * @return bool
     */
    public static function isAllowedExtension($extension)
    {
        return in_array(strtolower($extension), self::ALLOWED_EXTENSIONS);
    }
    
    /**
     * Get human readable max size
     * @return string
     */
    public static function getMaxSizeReadable()
    {
        $bytes = self::MAX_SIZE;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}

// ============================================================================
// CACHE KEYS (Reserved for future)
// ============================================================================

class CacheKeys
{
    public const USER_PROFILE = 'user_profile_';
    public const PRODUCT_LIST = 'product_list_';
    public const PRODUCT_DETAIL = 'product_detail_';
    public const TRUST_SCORE = 'trust_score_';
    public const NOTIFICATIONS = 'notifications_';
    public const DASHBOARD_STATS = 'dashboard_stats_';
    
    public static function userProfile($userId)
    {
        return self::USER_PROFILE . $userId;
    }
    
    public static function productDetail($productId)
    {
        return self::PRODUCT_DETAIL . $productId;
    }
    
    public static function trustScore($userId)
    {
        return self::TRUST_SCORE . $userId;
    }
}

// ============================================================================
// SESSION KEYS
// ============================================================================

class SessionKeys
{
    public const USER_ID = 'user_id';
    public const USER_NAME = 'user_name';
    public const USER_PHONE = 'user_phone';
    public const USER_ROLE = 'user_role';
    public const USER_TRUST_SCORE = 'user_trust_score';
    public const USER_VERIFICATION_TIER = 'user_verification_tier';
    public const CSRF_TOKEN = 'csrf_token';
    public const LAST_ACTIVITY = 'last_activity';
}

// ============================================================================
// COOKIE NAMES
// ============================================================================

class CookieNames
{
    public const SESSION = 'trustlink_session';
    public const REMEMBER = 'trustlink_remember';
    public const CSRF = 'trustlink_csrf';
}

// ============================================================================
// ERROR MESSAGES
// ============================================================================

class ErrorMessages
{
    // Auth Errors
    public const AUTH_INVALID_CREDENTIALS = 'Invalid phone number or password';
    public const AUTH_ACCOUNT_SUSPENDED = 'Your account has been suspended. Please contact support';
    public const AUTH_UNAUTHORIZED = 'You are not authorized to perform this action';
    public const AUTH_SESSION_EXPIRED = 'Your session has expired. Please login again';
    
    // Registration Errors
    public const REG_PHONE_EXISTS = 'Phone number already registered';
    public const REG_INVALID_PHONE = 'Invalid phone number format';
    public const REG_WEAK_PASSWORD = 'Password must be at least 6 characters';
    
    // Product Errors
    public const PRODUCT_NOT_FOUND = 'Product not found';
    public const PRODUCT_OUT_OF_STOCK = 'Product is out of stock';
    public const PRODUCT_INSUFFICIENT_STOCK = 'Insufficient stock available';
    public const PRODUCT_NOT_OWNER = 'You are not the owner of this product';
    
    // Order Errors
    public const ORDER_NOT_FOUND = 'Order not found';
    public const ORDER_NOT_OWNER = 'You are not authorized to modify this order';
    public const ORDER_CANNOT_CANCEL = 'Order cannot be cancelled at this stage';
    public const ORDER_CANNOT_REVIEW = 'Only completed orders can be reviewed';
    public const ORDER_ALREADY_REVIEWED = 'You have already reviewed this order';
    
    // Cart Errors
    public const CART_EMPTY = 'Your cart is empty';
    public const CART_ITEM_NOT_FOUND = 'Cart item not found';
    
    // Review Errors
    public const REVIEW_DUPLICATE = 'You have already reviewed this order';
    public const REVIEW_INVALID_RATING = 'Rating must be between 1 and 5';
    
    // General Errors
    public const INVALID_INPUT = 'Invalid input provided';
    public const RESOURCE_NOT_FOUND = 'Resource not found';
    public const SERVER_ERROR = 'Something went wrong. Please try again later';
    public const RATE_LIMIT_EXCEEDED = 'Too many requests. Please try again later';
}

// ============================================================================
// SUCCESS MESSAGES
// ============================================================================

class SuccessMessages
{
    // Auth
    public const LOGIN_SUCCESS = 'Login successful';
    public const LOGOUT_SUCCESS = 'Logout successful';
    public const REGISTER_SUCCESS = 'Registration successful. Please login';
    
    // Product
    public const PRODUCT_ADDED = 'Product added successfully';
    public const PRODUCT_UPDATED = 'Product updated successfully';
    public const PRODUCT_DELETED = 'Product deleted successfully';
    
    // Order
    public const ORDER_PLACED = 'Order placed successfully';
    public const ORDER_ACCEPTED = 'Order accepted successfully';
    public const ORDER_COMPLETED = 'Order marked as completed';
    public const ORDER_CANCELLED = 'Order cancelled successfully';
    
    // Review
    public const REVIEW_ADDED = 'Review added successfully. Thank you for your feedback!';
    
    // Profile
    public const PROFILE_UPDATED = 'Profile updated successfully';
    public const PHOTO_UPLOADED = 'Profile photo uploaded successfully';
    
    // Cart
    public const CART_ADDED = 'Item added to cart';
    public const CART_UPDATED = 'Cart updated';
    public const CART_REMOVED = 'Item removed from cart';
    public const CART_CLEARED = 'Cart cleared';
}

// ============================================================================
// REGEX PATTERNS
// ============================================================================

class RegexPatterns
{
    // Kenyan phone number: 07XX XXX XXX or 01XX XXX XXX
    public const PHONE_KENYA = '/^(07|01)[0-9]{8}$/';
    
    // Email validation
    public const EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    
    // Password: at least 6 characters
    public const PASSWORD = '/^.{6,}$/';
    
    // Name: letters, spaces, hyphens
    public const NAME = '/^[a-zA-Z\s\-]{2,100}$/';
    
    // Order code: TRUST-YYYY-XXXXXX
    public const ORDER_CODE = '/^TRUST-\d{4}-\d{6}$/';
}

// ============================================================================
// INITIALIZE CONSTANTS (Set timezone if not already set)
// ============================================================================

// Set timezone from environment
$timezone = EnvironmentLoader::get('APP_TIMEZONE', 'Africa/Nairobi');
date_default_timezone_set($timezone);

// Set default locale
setlocale(LC_ALL, 'en_US.utf8');

// Set default charset
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
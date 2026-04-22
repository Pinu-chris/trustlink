<?php
/**
 * TRUSTLINK - Standard API Response Handler
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Unified response format for all API endpoints
 * Features:
 * - Standard JSON response structure
 * - HTTP status codes
 * - Error handling
 * - Pagination support
 * - Data validation responses
 * - CORS headers
 * 
 * Usage:
 *   Response::success($data, 'Operation successful');
 *   Response::error('Something went wrong', 400);
 *   Response::notFound('User not found');
 *   Response::unauthorized();
 */

namespace TrustLink\Utils;

// Load constants for error messages
require_once __DIR__ . '/../config/constants.php';

use TrustLink\Config\ApiResponseCode;
use TrustLink\Config\ErrorMessages;

class Response
{
    /**
     * @var bool Whether headers have been sent
     */
    private static $headersSent = false;
    
    /**
     * Send JSON response
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return void
     */
    private static function send($data, $statusCode = 200, $headers = [])
    {
        // Set HTTP status code
        http_response_code($statusCode);
        
        // Set default JSON header
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
        }
        
        // Set CORS headers (for development, restrict in production)
        if (!self::$headersSent) {
            self::setCorsHeaders();
        }
        
        // Send custom headers
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        
        self::$headersSent = true;
        
        // Encode and output JSON
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Handle JSON encoding errors
        if ($json === false) {
            $json = json_encode([
                'success' => false,
                'message' => 'Response encoding failed',
                'error' => json_last_error_msg()
            ]);
        }
        
        echo $json;
        exit;
    }
    
    /**
     * Set CORS headers for cross-origin requests
     * 
     * @return void
     */
    private static function setCorsHeaders()
    {
        // Allow from any origin (for development)
        // In production, restrict to specific domains
        if (getenv('APP_ENV') !== 'production') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
        }
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Send success response
     * 
     * @param mixed $data Response data (array, object, or primitive)
     * @param string $message Success message
     * @param int $statusCode HTTP status code (default: 200)
     * @param array $meta Additional metadata (pagination, etc.)
     * @return void
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200, $meta = [])
    {
        $response = [
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
        ];
        
        // Add data if provided
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        // Add metadata if provided
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        self::send($response, $statusCode);
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default: 400)
     * @param array $errors Additional error details (for validation)
     * @return void
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = [])
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode,
        ];
        
        // Add validation errors if provided
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        self::send($response, $statusCode);
    }
    
    /**
     * Send validation error response (422 Unprocessable Entity)
     * 
     * @param array $errors Validation errors (field => message)
     * @param string $message Custom error message
     * @return void
     */
    public static function validationError($errors, $message = 'Validation failed')
    {
        self::error($message, ApiResponseCode::UNPROCESSABLE_ENTITY, $errors);
    }
    
    /**
     * Send not found response (404)
     * 
     * @param string $resource Resource type (e.g., 'User', 'Product')
     * @return void
     */
    public static function notFound($resource = 'Resource')
    {
        self::error("$resource not found", ApiResponseCode::NOT_FOUND);
    }
    
    /**
     * Send unauthorized response (401)
     * 
     * @param string $message Custom unauthorized message
     * @return void
     */
    public static function unauthorized($message = null)
    {
        $msg = $message ?? ErrorMessages::AUTH_UNAUTHORIZED;
        self::error($msg, ApiResponseCode::UNAUTHORIZED);
    }
    
    /**
     * Send forbidden response (403)
     * 
     * @param string $message Custom forbidden message
     * @return void
     */
    public static function forbidden($message = null)
    {
        $msg = $message ?? 'You do not have permission to perform this action';
        self::error($msg, ApiResponseCode::FORBIDDEN);
    }
    
    /**
     * Send bad request response (400)
     * 
     * @param string $message Custom error message
     * @return void
     */
    public static function badRequest($message = null)
    {
        $msg = $message ?? ErrorMessages::INVALID_INPUT;
        self::error($msg, ApiResponseCode::BAD_REQUEST);
    }
    
    /**
     * Send conflict response (409)
     * 
     * @param string $message Custom conflict message
     * @return void
     */
    public static function conflict($message = null)
    {
        $msg = $message ?? 'Resource already exists';
        self::error($msg, ApiResponseCode::CONFLICT);
    }
    
    /**
     * Send created response (201)
     * 
     * @param mixed $data Created resource data
     * @param string $message Success message
     * @return void
     */
    public static function created($data = null, $message = 'Resource created successfully')
    {
        self::success($data, $message, ApiResponseCode::CREATED);
    }
    
    /**
     * Send no content response (204)
     * 
     * @return void
     */
    public static function noContent()
    {
        self::send([
            'success' => true,
            'message' => 'No content',
            'status_code' => ApiResponseCode::NO_CONTENT
        ], ApiResponseCode::NO_CONTENT);
    }
    
    /**
     * Send paginated response
     * 
     * @param array $data Items for current page
     * @param int $total Total number of items
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @param string $message Success message
     * @return void
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'Success')
    {
        $totalPages = ceil($total / $perPage);
        
        $meta = [
            'pagination' => [
                'total' => (int) $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'total_pages' => (int) $totalPages,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1,
            ]
        ];
        
        self::success($data, $message, ApiResponseCode::SUCCESS, $meta);
    }
    
    /**
     * Send rate limit exceeded response (429)
     * 
     * @param int $retryAfter Seconds until rate limit resets
     * @return void
     */
    public static function rateLimitExceeded($retryAfter = 60)
    {
        $headers = ['Retry-After' => $retryAfter];
        self::error(ErrorMessages::RATE_LIMIT_EXCEEDED, ApiResponseCode::TOO_MANY_REQUESTS, [], $headers);
    }
    
    /**
     * Send server error response (500)
     * 
     * @param string $message Custom error message
     * @param bool $showDetails Show error details in development
     * @param \Exception $exception Optional exception for logging
     * @return void
     */
    public static function serverError($message = null, $showDetails = false, $exception = null)
    {
        $msg = $message ?? ErrorMessages::SERVER_ERROR;
        
        $response = [
            'success' => false,
            'message' => $msg,
            'status_code' => ApiResponseCode::INTERNAL_SERVER_ERROR,
        ];
        
        // Log the exception if provided
        if ($exception !== null) {
            self::logException($exception);
            
            // Show details only in development mode
            if ($showDetails && getenv('APP_ENV') !== 'production') {
                $response['debug'] = [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }
        }
        
        self::send($response, ApiResponseCode::INTERNAL_SERVER_ERROR);
    }
    
    /**
     * Log exception to file
     * 
     * @param \Exception $exception
     * @return void
     */
    private static function logException($exception)
    {
        $logPath = getenv('LOG_PATH') ?: __DIR__ . '/../logs/error.log';
        $logDir = dirname($logPath);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s\n\n",
            $timestamp,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        error_log($logMessage, 3, $logPath);
    }
}

// ============================================================================
// CONVENIENCE FUNCTIONS (Global namespace for easier use)
// ============================================================================

if (!function_exists('response')) {
    /**
     * Get Response utility instance (for method chaining)
     * 
     * @return Response
     */
    function response()
    {
        return new Response();
    }
}

if (!function_exists('success')) {
    /**
     * Send success response
     * 
     * @param mixed $data
     * @param string $message
     * @return void
     */
    function success($data = null, $message = 'Success')
    {
        Response::success($data, $message);
    }
}

if (!function_exists('error')) {
    /**
     * Send error response
     * 
     * @param string $message
     * @param int $statusCode
     * @return void
     */
    function error($message = 'An error occurred', $statusCode = 400)
    {
        Response::error($message, $statusCode);
    }
}

if (!function_exists('validationError')) {
    /**
     * Send validation error response
     * 
     * @param array $errors
     * @param string $message
     * @return void
     */
    function validationError($errors, $message = 'Validation failed')
    {
        Response::validationError($errors, $message);
    }
}

if (!function_exists('notFound')) {
    /**
     * Send not found response
     * 
     * @param string $resource
     * @return void
     */
    function notFound($resource = 'Resource')
    {
        Response::notFound($resource);
    }
}

if (!function_exists('unauthorized')) {
    /**
     * Send unauthorized response
     * 
     * @param string $message
     * @return void
     */
    function unauthorized($message = null)
    {
        Response::unauthorized($message);
    }
}

if (!function_exists('forbidden')) {
    /**
     * Send forbidden response
     * 
     * @param string $message
     * @return void
     */
    function forbidden($message = null)
    {
        Response::forbidden($message);
    }
}

if (!function_exists('badRequest')) {
    /**
     * Send bad request response
     * 
     * @param string $message
     * @return void
     */
    function badRequest($message = null)
    {
        Response::badRequest($message);
    }
}

if (!function_exists('conflict')) {
    /**
     * Send conflict response
     * 
     * @param string $message
     * @return void
     */
    function conflict($message = null)
    {
        Response::conflict($message);
    }
}

if (!function_exists('created')) {
    /**
     * Send created response
     * 
     * @param mixed $data
     * @param string $message
     * @return void
     */
    function created($data = null, $message = 'Resource created successfully')
    {
        Response::created($data, $message);
    }
}

if (!function_exists('paginated')) {
    /**
     * Send paginated response
     * 
     * @param array $data
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @param string $message
     * @return void
     */
    function paginated($data, $total, $page, $perPage, $message = 'Success')
    {
        Response::paginated($data, $total, $page, $perPage, $message);
    }
}

// ============================================================================
// USAGE EXAMPLES (Commented out)
// ============================================================================

/*
// Success with data
Response::success(['user' => $user], 'User retrieved successfully');

// Success with pagination
Response::paginated($products, $totalProducts, $page, $perPage);

// Error with message
Response::error('Invalid credentials', 401);

// Validation errors
Response::validationError([
    'phone' => 'Phone number is required',
    'password' => 'Password must be at least 6 characters'
]);

// Not found
Response::notFound('Product');

// Created
Response::created(['id' => 123], 'Product added successfully');

// Server error with exception logging
try {
    // some code
} catch (Exception $e) {
    Response::serverError('Failed to process request', true, $e);
}
*/
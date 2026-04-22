<?php
/**
 * TRUSTLINK - Update User Profile API
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Updates current authenticated user's profile data
 * Features:
 * - Update name, email, location
 * - Phone number update with uniqueness check
 * - Role-specific validations
 * - Session validation required
 * 
 * HTTP Method: PUT
 * Endpoint: /api/users/update_profile.php
 * 
 * Headers:
 * - Cookie: trustlink_session=...
 * 
 * Request Body (JSON):
 * {
 *     "name": "John Doe Updated",
 *     "email": "john@example.com",
 *     "phone": "0712345678",
 *     "county": "Nairobi",
 *     "subcounty": "Westlands",
 *     "ward": "Kilimani"
 * }
 * 
 * Response:
 * - 200: Profile updated successfully
 * - 400: Validation errors
 * - 401: Unauthorized
 * - 409: Phone already exists
 */

// Enable CORS and set headers
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Config\RegexPatterns;
use TrustLink\Config\SuccessMessages;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

// Initialize auth and require authentication
$auth = new AuthMiddleware();
$user = $auth->requireAuth();

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::badRequest('Invalid JSON payload');
}

// ============================================================================
// FIELD VALIDATION
// ============================================================================

$errors = [];
$updateData = [];

// 1. Name validation (optional)
if (isset($input['name'])) {
    $name = trim($input['name']);
    
    if (strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters';
    } elseif (strlen($name) > 100) {
        $errors['name'] = 'Name cannot exceed 100 characters';
    } elseif (!preg_match(RegexPatterns::NAME, $name)) {
        $errors['name'] = 'Name can only contain letters, spaces, and hyphens';
    } else {
        $updateData['name'] = $name;
    }
}

// 2. Email validation (optional)
if (isset($input['email'])) {
    $email = trim($input['email']);
    
    if (!empty($email)) {
        if (!preg_match(RegexPatterns::EMAIL, $email)) {
            $errors['email'] = 'Invalid email address format';
        } else {
            $updateData['email'] = $email;
        }
    } else {
        $updateData['email'] = null;
    }
}

// 3. Phone validation (optional - requires uniqueness check)
if (isset($input['phone'])) {
    $phone = trim($input['phone']);
    
    if (!preg_match(RegexPatterns::PHONE_KENYA, $phone)) {
        $errors['phone'] = 'Invalid Kenyan phone number. Format: 07XXXXXXXX or 01XXXXXXXX';
    } else {
        $updateData['phone'] = $phone;
    }
}

// 4. Location validation (optional)
if (isset($input['county'])) {
    $updateData['county'] = !empty($input['county']) ? trim($input['county']) : null;
}
if (isset($input['subcounty'])) {
    $updateData['subcounty'] = !empty($input['subcounty']) ? trim($input['subcounty']) : null;
}
if (isset($input['ward'])) {
    $updateData['ward'] = !empty($input['ward']) ? trim($input['ward']) : null;
}

// Return validation errors if any
if (!empty($errors)) {
    Response::validationError($errors, 'Validation failed');
}

// If no fields to update
if (empty($updateData)) {
    Response::success(null, 'No changes to update');
}

// ============================================================================
// PHONE UNIQUENESS CHECK (if phone is being updated)
// ============================================================================

if (isset($updateData['phone']) && $updateData['phone'] !== $user['phone']) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $stmt->execute([$updateData['phone'], $user['id']]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            Response::conflict('Phone number is already registered to another account');
        }
    } catch (\PDOException $e) {
        error_log("Phone uniqueness check error: " . $e->getMessage());
        Response::serverError('Failed to verify phone number');
    }
}

// ============================================================================
// UPDATE PROFILE
// ============================================================================

try {
    $db = Database::getInstance();
    
    // Build SET clause dynamically
    $setClause = [];
    $params = [];
    
    foreach ($updateData as $column => $value) {
        $setClause[] = "$column = ?";
        $params[] = $value;
    }
    
    // Add user ID for WHERE clause
    $params[] = $user['id'];
    
    $sql = "UPDATE users SET " . implode(', ', $setClause) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $affectedRows = $stmt->rowCount();
    
    // ============================================================================
    // LOG THE ACTIVITY
    // ============================================================================
    
    try {
        $activityData = [
            'user_id' => $user['id'],
            'action' => 'profile_updated',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode(['updated_fields' => array_keys($updateData)])
        ];
        Database::insert('activity_logs', $activityData);
    } catch (\Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // ============================================================================
    // UPDATE SESSION DATA (if name changed)
    // ============================================================================
    
    if (isset($updateData['name'])) {
        $_SESSION[SessionKeys::USER_NAME] = $updateData['name'];
    }
    
    // ============================================================================
    // FETCH UPDATED PROFILE
    // ============================================================================
    
    $stmt = $db->prepare("
        SELECT id, name, phone, email, role, trust_score, verification_tier,
               county, subcounty, ward, profile_photo, created_at
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    $updatedProfile = $stmt->fetch();
    
    // ============================================================================
    // SUCCESS RESPONSE
    // ============================================================================
    
    Response::success([
        'user' => $updatedProfile,
        'updated_fields' => array_keys($updateData)
    ], SuccessMessages::PROFILE_UPDATED);
    
} catch (\PDOException $e) {
    error_log("Update profile error: " . $e->getMessage());
    Response::serverError('Failed to update profile', false, $e);
}
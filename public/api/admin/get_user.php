<?php
require_once dirname(__DIR__, 2) . '/utils/auth_middleware.php';

// 2. ACTIVATE THE SHIELD (Crucial Step!)
// This one line ensures only Admins can proceed. 
// Everyone else (including Farmers/Buyers) gets kicked out immediately.
$adminData = require_admin(); // ✅ This looks in the Global space where we put it




header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$auth->requireAdmin();

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($userId <= 0) {
    Response::badRequest('User ID required');
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT id, name, phone, email, role, trust_score, verification_tier,
               county, subcounty, ward, profile_photo, status, id_verified,
               admin_type, created_at, last_login_at
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::notFound('User');
    }

    // Get role display name
    $roleMap = [
        'buyer' => 'Buyer',
        'farmer' => 'Farmer',
        'admin' => 'Administrator',
        'service_provider' => 'Service Provider'
    ];
    $user['role_display'] = $roleMap[$user['role']] ?? ucfirst($user['role']);

    // Get statistics based on role
    $stats = [];
    if ($user['role'] === 'farmer') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_orders,
                   COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                   COALESCE(SUM(total), 0) as total_earnings
            FROM orders WHERE farmer_id = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
    } elseif ($user['role'] === 'buyer') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_orders,
                   COALESCE(SUM(total), 0) as total_spent
            FROM orders WHERE buyer_id = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
    }

    $user['statistics'] = $stats ?: [];

    // Build profile photo URL if exists
    $user['profile_photo'] = $user['profile_photo']
        ? (getenv('APP_URL') ?: 'http://localhost/trustfiles') . '/assets/images/uploads/profile/' . $user['profile_photo']
        : null;

    // Ensure numeric fields are cast properly
    $user['trust_score'] = (float) $user['trust_score'];
    $user['status'] = (bool) $user['status'];
    $user['id_verified'] = (bool) $user['id_verified'];

    Response::success($user, 'User details retrieved');

} catch (\PDOException $e) {
    error_log("Get user error: " . $e->getMessage());
    Response::serverError('Failed to retrieve user details: ' . $e->getMessage());
} catch (\Exception $e) {
    error_log("Get user general error: " . $e->getMessage());
    Response::serverError('An error occurred');
}
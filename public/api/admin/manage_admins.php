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
$currentUser = $auth->requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;
$targetUserId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$newPassword = $input['password'] ?? null;

if (!$targetUserId) {
    Response::badRequest('User ID is required');
}

try {
    $db = Database::getInstance();

    // Fetch target user
    $stmt = $db->prepare("SELECT id, name, role, admin_type FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch();
    if (!$targetUser) {
        Response::notFound('User');
    }

    // Prevent self‑action for promote/demote (except password change)
    if ($targetUserId == $currentUser['id'] && $action !== 'change_password') {
        Response::forbidden('You cannot modify your own admin status');
    }

    switch ($action) {
        case 'promote':
            // Only founder can promote to admin
            if ($currentUser['admin_type'] !== 'founder') {
                Response::forbidden('Only the founder can promote users to admin');
            }
            if ($targetUser['role'] === 'admin') {
                Response::conflict('User is already an admin');
            }
            $stmt = $db->prepare("UPDATE users SET role = 'admin', admin_type = 'admin', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = "User '{$targetUser['name']}' promoted to admin";
            break;

        case 'demote':
            if ($currentUser['admin_type'] !== 'founder') {
                Response::forbidden('Only the founder can demote admins');
            }
            if ($targetUser['role'] !== 'admin') {
                Response::conflict('User is not an admin');
            }
            if ($targetUser['admin_type'] === 'founder') {
                Response::forbidden('Cannot demote the founder');
            }
            $stmt = $db->prepare("UPDATE users SET role = 'buyer', admin_type = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = "User '{$targetUser['name']}' demoted from admin";
            break;

        case 'change_password':
            // Allow only founder to change another admin's password; admins can change their own
            if ($targetUser['role'] === 'admin' && $targetUserId != $currentUser['id'] && $currentUser['admin_type'] !== 'founder') {
                Response::forbidden('Only the founder can change another admin’s password');
            }
            if (!$newPassword || strlen($newPassword) < 6) {
                Response::badRequest('Password must be at least 6 characters');
            }
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed, $targetUserId]);
            $message = "Password changed for '{$targetUser['name']}'";
            break;

        default:
            Response::badRequest('Invalid action');
    }

    // Log the activity (optional)
    $logStmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, ip_address, user_agent, details, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $currentUser['id'],
        'admin_' . $action,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode(['target_user_id' => $targetUserId, 'target_name' => $targetUser['name']])
    ]);

    Response::success(['user_id' => $targetUserId, 'user_name' => $targetUser['name']], $message);

} catch (\PDOException $e) {
    error_log("Admin management error: " . $e->getMessage());
    Response::serverError('Failed to perform action', false, $e);
}
<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$auth->requireAdmin(); // only admins can call this

$input = json_decode(file_get_contents('php://input'), true);
$userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;

if ($userId <= 0) {
    Response::badRequest('User ID required');
}

try {
    $db = Database::getInstance();

    // Fetch user
    $stmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::notFound('User');
    }

    // Prevent resetting founder's password? Optional, but you may want to protect founder.
    if ($user['role'] === 'admin' && $user['admin_type'] === 'founder') {
        Response::forbidden('Cannot reset the founder\'s password');
    }

    // Generate a random temporary password (e.g., 10 chars)
    $tempPassword = bin2hex(random_bytes(5)); // 10 hex chars
    // Optionally add a special char and number to make it stronger
    $tempPassword = $tempPassword . '!' . rand(10,99);

    $hashed = password_hash($tempPassword, PASSWORD_DEFAULT);

    // Update password and set must_change_password flag
    $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = true WHERE id = ?");
    $stmt->execute([$hashed, $userId]);

    // Log the action
    $logStmt = $db->prepare("
        INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $logStmt->execute([
        $_SESSION['user_id'],
        'force_password_reset',
        $userId,
        json_encode(['user_name' => $user['name']]),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    Response::success([
        'user_id' => $userId,
        'user_name' => $user['name'],
        'temporary_password' => $tempPassword
    ], 'Temporary password generated. User must change it on next login.');

} catch (\PDOException $e) {
    error_log("Force reset error: " . $e->getMessage());
    Response::serverError('Failed to reset password');
}
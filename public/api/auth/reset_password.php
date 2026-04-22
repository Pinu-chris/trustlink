<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';
$confirm = $input['confirm_password'] ?? '';

if (!$token || !$password || !$confirm) {
    Response::badRequest('All fields are required');
}

if (strlen($password) < 6) {
    Response::badRequest('Password must be at least 6 characters');
}

if ($password !== $confirm) {
    Response::badRequest('Passwords do not match');
}

try {
    $db = Database::getInstance();

    // Find the token
    $stmt = $db->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        Response::badRequest('Invalid or expired token');
    }

    if (strtotime($reset['expires_at']) < time()) {
        // Token expired
        Response::badRequest('Token has expired. Please request a new reset.');
    }

    // Hash the new password
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Update user password and clear the must_change_password flag
    $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = false WHERE id = ?");
    $stmt->execute([$hashed, $reset['user_id']]);

    // Delete the used token
    $stmt = $db->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);

    Response::success(null, 'Password has been reset successfully');

} catch (\PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    Response::serverError('Failed to reset password');
}
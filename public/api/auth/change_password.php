<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../utils/response.php';
require_once __DIR__ . '/../../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\Response;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$user = $auth->requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$newPassword = $input['password'] ?? '';

if (strlen($newPassword) < 6) {
    Response::badRequest('Password must be at least 6 characters');
}

try {
    $db = Database::getInstance();
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = false WHERE id = ?");
    $stmt->execute([$hashed, $user['id']]);

    // Remove the force flag from session
    unset($_SESSION['force_password_change']);

    Response::success(null, 'Password changed successfully');
} catch (\PDOException $e) {
    error_log("Change password error: " . $e->getMessage());
    Response::serverError('Failed to change password');
}
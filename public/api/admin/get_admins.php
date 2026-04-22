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

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT id, name, phone, email, role, admin_type, created_at
        FROM users
        WHERE role = 'admin'
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll();

    Response::success($admins, 'Admins retrieved');
} catch (\PDOException $e) {
    error_log("Get admins error: " . $e->getMessage());
    Response::serverError('Failed to retrieve admins');
}
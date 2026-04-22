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

// Only founder can create new admins
if ($currentUser['admin_type'] !== 'founder') {
    Response::forbidden('Only the founder can create new admins');
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';

if (!$name || !$phone || !$password) {
    Response::badRequest('Name, phone, and password are required');
}
if (strlen($password) < 6) {
    Response::badRequest('Password must be at least 6 characters');
}
if (!preg_match('/^(07|01)[0-9]{8}$/', $phone)) {
    Response::badRequest('Invalid Kenyan phone number');
}

try {
    $db = Database::getInstance();

    // Check if phone already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        Response::conflict('Phone number already registered');
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT INTO users (name, phone, password, role, admin_type, status, created_at)
        VALUES (?, ?, ?, 'admin', 'admin', true, NOW())
        RETURNING id
    ");
    $stmt->execute([$name, $phone, $hashed]);
    $newId = $stmt->fetchColumn();

    // Log activity
    $logStmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, ip_address, user_agent, details, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $currentUser['id'],
        'admin_created',
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode(['new_admin_id' => $newId, 'name' => $name, 'phone' => $phone])
    ]);

    Response::success(['id' => $newId, 'name' => $name, 'phone' => $phone], 'Admin created successfully');

} catch (\PDOException $e) {
    error_log("Create admin error: " . $e->getMessage());
    Response::serverError('Failed to create admin');
}
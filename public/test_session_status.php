<?php
session_start();
header('Content-Type: text/html');

// Load database first
require_once __DIR__ . '/../config/db.php';

use TrustLink\Config\Database;

echo "<h2>Session Status Test</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Status: " . session_status() . "\n\n";

echo "SESSION DATA:\n";
print_r($_SESSION);

echo "\n\nCOOKIE DATA:\n";
print_r($_COOKIE);

echo "\n\nSpecific Checks:\n";
echo "user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "\n";
echo "user_role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'NOT SET') . "\n";
echo "user_name: " . (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'NOT SET') . "\n";

echo "\n\nSession Save Path: " . session_save_path() . "\n";
echo "Session Cookie Params: " . print_r(session_get_cookie_params(), true) . "\n";
echo "</pre>";

// Test connection to database
echo "<h3>Database Connection Test:</h3>";
try {
    $db = Database::getInstance();
    echo "<p style='color:green'>✓ Database connected successfully</p>";
    
    // Check if user exists in database
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            echo "<p style='color:green'>✓ User found in database: ID={$user['id']}, Role={$user['role']}, Name={$user['name']}</p>";
        } else {
            echo "<p style='color:red'>✗ User NOT found in database!</p>";
        }
    } else {
        echo "<p style='color:orange'>⚠ No user_id in session, cannot check database</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database error: " . $e->getMessage() . "</p>";
}
?>
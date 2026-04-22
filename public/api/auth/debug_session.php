<?php
// Start session with proper settings
session_name('trustlink_session');
session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Set JSON header
header('Content-Type: application/json');

// Collect all session information
$debug = [
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_status' => session_status(),
    'session_save_path' => session_save_path(),
    'session_cookie_params' => session_get_cookie_params(),
    'session_data' => $_SESSION,
    'cookies_received' => $_COOKIE,
    'server_info' => [
        'request_uri' => $_SERVER['REQUEST_URI'],
        'script_name' => $_SERVER['SCRIPT_NAME'],
        'http_host' => $_SERVER['HTTP_HOST'],
    ],
    'has_session_cookie' => isset($_COOKIE[session_name()]),
    'user_logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_role' => $_SESSION['user_role'] ?? null,
];

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
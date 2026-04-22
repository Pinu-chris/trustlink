<?php
/**
 * Global Session Configuration
 * Include this at the top of ALL PHP files that need session access.
 */

// Only configure session if headers have NOT been sent yet
if (!headers_sent()) {
    // Set session name (avoid default PHPSESSID)
    session_name('trustlink_session');

    // Set secure cookie parameters based on PHP version
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 7200,            // 2 hours
            'path'     => '/',
            'domain'   => '',              // current domain
            'secure'   => false,           // set to true when using HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    } else {
        session_set_cookie_params(7200, '/', '', false, true);
    }

    // Set additional ini directives
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions remain unchanged
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isFarmer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'farmer';
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
?>
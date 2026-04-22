<?php
/**
 * Global Session Configuration
 * Include this at the top of ALL PHP files that need session access
 */

/**
 * Secure Session Configuration
 * Must be included BEFORE session_start() in every page that uses sessions.
 */

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
        'samesite' => 'Strict'         // Increased from Lax to Strict
    ]);
} else {
    // Fallback for older PHP versions
    session_set_cookie_params(7200, '/', '', false, true);
}

// Set additional ini directives for maximum security
ini_set('session.use_strict_mode', 1);      // prevent session fixation
ini_set('session.cookie_httponly', 1);      // prevent JS access to cookies
ini_set('session.cookie_secure', 0);        // set to 1 when HTTPS is enabled
ini_set('session.use_only_cookies', 1);     // only cookies, no URL parameter
ini_set('session.cookie_samesite', 'Strict');

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Function to check if user is farmer
function isFarmer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'farmer';
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
?>
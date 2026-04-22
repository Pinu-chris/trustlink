<?php
// 1. Start session to access it
session_start();

// 2. Clear all session variables
$_SESSION = array();

// 3. Destroy the cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 4. Destroy the session on the server
session_destroy();

// 5. Redirect to homepage or login
header("Location: index.php");
exit;
?>
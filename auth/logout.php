<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include functions
require_once '../includes/functions.php';

// Clear cookies if they exist
if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 3600, '/');
}

// Remember Me cookies are disabled because remember_token and token_expires
// columns don't exist in the database tables yet
/*
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
*/

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to homepage with message
setMessage("You have been successfully logged out.", "success");
header("Location: /index.php");
exit;
?>
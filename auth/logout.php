<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include functions
require_once '../includes/functions.php';

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie('user_email', '', time() - 3600, '/');
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to homepage with message
setMessage("You have been successfully logged out.", "success");
header("Location: /index.php");
exit;
?>
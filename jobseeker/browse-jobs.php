<?php
// Set page title
$page_title = "Browse Jobs";
$page_header = "Browse Jobs";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is a job seeker
requireJobSeeker();

// Redirect to main browse jobs page
header("Location: ../browse-jobs.php");
exit;
?>
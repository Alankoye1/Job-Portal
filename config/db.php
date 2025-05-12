<?php
// Database connection parameters
$host = "localhost";
$username = "root";
$password = "Alankoye2005@";
$database = "job_portal";

// Create database connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to ensure proper handling of all characters
$conn->set_charset("utf8mb4");

// Function to handle database errors
function handleDBError($query) {
    global $conn;
    echo "Error executing query: " . $query . "<br>";
    echo "Error details: " . $conn->error;
    exit();
}

// Function to sanitize input data
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}
?>
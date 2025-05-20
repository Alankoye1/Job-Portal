<?php
// Database connection details
require_once 'config/db.php';

echo "Starting to execute SQL script...\n";

// Read SQL file content
$sql_file = file_get_contents('add_experience_level.sql');

// Split SQL file into individual statements
$statements = explode(';', $sql_file);

// Execute each statement
$success = true;
foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        if (!$conn->query($statement)) {
            echo "Error executing statement: " . $conn->error . "\n";
            $success = false;
        }
    }
}

if ($success) {
    echo "SQL script executed successfully!\n";
} else {
    echo "There were errors executing the SQL script.\n";
}

// Close connection
$conn->close();
?> 
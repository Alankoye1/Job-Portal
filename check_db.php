<?php
// Include database connection
require_once 'config/db.php';

// Check if tables exist
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);

echo "<h2>Tables in database:</h2>";
if ($tables_result && $tables_result->num_rows > 0) {
    echo "<ul>";
    while ($table = $tables_result->fetch_row()) {
        echo "<li>" . $table[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "No tables found in database.";
}

// Check jobs table structure if it exists
$check_jobs_query = "SHOW COLUMNS FROM jobs";
$check_jobs_result = $conn->query($check_jobs_query);

echo "<h2>Jobs table structure:</h2>";
if ($check_jobs_result && $check_jobs_result->num_rows > 0) {
    echo "<ul>";
    while ($column = $check_jobs_result->fetch_assoc()) {
        echo "<li>" . $column['Field'] . " - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "Jobs table doesn't exist or has no columns.";
}

// Close the connection
$conn->close();
?>

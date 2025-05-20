<?php
// Include database connection
require_once 'config/db.php';

// SQL to create the saved_jobs table
$sql = "CREATE TABLE IF NOT EXISTS `saved_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jobseeker_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jobseeker_job_unique` (`jobseeker_id`, `job_id`),
  KEY `job_id` (`job_id`),
  CONSTRAINT `saved_jobs_ibfk_1` FOREIGN KEY (`jobseeker_id`) REFERENCES `jobseekers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saved_jobs_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Execute the query
if ($conn->query($sql) === TRUE) {
    echo "Table 'saved_jobs' created successfully!";

    // Insert some sample data
    $sample_data = "INSERT INTO `saved_jobs` (`jobseeker_id`, `job_id`) VALUES
    (1, 3),
    (1, 4),
    (2, 1),
    (3, 2)";
    
    if ($conn->query($sample_data) === TRUE) {
        echo "<br>Sample data inserted successfully!";
    } else {
        echo "<br>Error inserting sample data: " . $conn->error;
    }
} else {
    echo "Error creating table: " . $conn->error;
}

// Close connection
$conn->close();

echo "<br><br>You can now return to <a href='jobseeker/saved-jobs.php'>Saved Jobs</a> page.";
?> 
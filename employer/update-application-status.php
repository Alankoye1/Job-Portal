<?php
// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is an employer
requireEmployer();

// Get application ID and status
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

if ($application_id <= 0) {
    setMessage("Invalid application ID.", "danger");
    header("Location: applications.php");
    exit;
}

// If status is provided in URL, update it directly
if (!empty($status)) {
    $valid_statuses = ['pending', 'reviewed', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected'];
    
    if (in_array($status, $valid_statuses)) {
        // Verify employer owns this application's job
        $verify_query = "SELECT a.id 
                         FROM applications a
                         JOIN jobs j ON a.job_id = j.id
                         WHERE a.id = ? AND j.employer_id = ?";
        $stmt = $conn->prepare($verify_query);
        $stmt->bind_param("ii", $application_id, $_SESSION['user_id']);
        $stmt->execute();
        $verify_result = $stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            // Update the status
            $update_query = "UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $status, $application_id);
            
            if ($stmt->execute()) {
                setMessage("Application status updated successfully.", "success");
            } else {
                setMessage("Failed to update application status.", "danger");
            }
        } else {
            setMessage("You don't have permission to update this application.", "danger");
        }
    } else {
        setMessage("Invalid status value.", "danger");
    }
}

// Redirect to view-application.php
header("Location: view-application.php?id=" . $application_id);
exit; 
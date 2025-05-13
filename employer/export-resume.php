<?php
// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is an employer
requireEmployer();

// Get application ID
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id <= 0) {
    setMessage("Invalid application ID.", "danger");
    header("Location: applications.php");
    exit;
}

// Get application details with resume information
$query = "SELECT a.resume, js.resume as jobseeker_resume, 
                 j.employer_id, a.jobseeker_id,
                 CONCAT(js.first_name, ' ', js.last_name) as applicant_name
          FROM applications a
          JOIN jobs j ON a.job_id = j.id
          JOIN jobseekers js ON a.jobseeker_id = js.id
          WHERE a.id = ? AND j.employer_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $application_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setMessage("Application not found or you don't have permission to view it.", "danger");
    header("Location: applications.php");
    exit;
}

$application = $result->fetch_assoc();

// Determine which resume to use (application resume or profile resume)
$resume_filename = !empty($application['resume']) ? $application['resume'] : $application['jobseeker_resume'];

if (empty($resume_filename)) {
    setMessage("No resume found for this application.", "warning");
    header("Location: view-application.php?id=" . $application_id);
    exit;
}

// Construct the file path
$file_path = $_SERVER['DOCUMENT_ROOT'] . '/project/assets/uploads/resumes/' . $resume_filename;

// Check if file exists
if (!file_exists($file_path)) {
    setMessage("Resume file not found.", "danger");
    header("Location: view-application.php?id=" . $application_id);
    exit;
}

// Get file extension to determine content type
$file_extension = strtolower(pathinfo($resume_filename, PATHINFO_EXTENSION));
$content_type = 'application/octet-stream'; // Default

if ($file_extension == 'pdf') {
    $content_type = 'application/pdf';
} elseif (in_array($file_extension, ['doc', 'docx'])) {
    $content_type = 'application/msword';
}

// Clean the applicant name for the filename
$clean_name = preg_replace('/[^a-zA-Z0-9]/', '_', $application['applicant_name']);
$download_filename = $clean_name . '_Resume.' . $file_extension;

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $download_filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Clear output buffer
ob_clean();
flush();

// Read and output file
readfile($file_path);
exit; 
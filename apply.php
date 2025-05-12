<?php
// Set page title
$page_title = "Apply for Job";

// Include database connection
require_once 'config/db.php';
// Include functions
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a job seeker
requireJobSeeker();

// Check if job ID is provided
if (!isset($_GET['job_id']) || empty($_GET['job_id'])) {
    setMessage("Job ID is required", "danger");
    header("Location: browse-jobs.php");
    exit;
}

$job_id = intval($_GET['job_id']);

// Get job details
$query = "SELECT j.*, e.company_name 
          FROM jobs j 
          JOIN employers e ON j.employer_id = e.id 
          WHERE j.id = ? AND j.status = 'active' AND j.expires_at > NOW()";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage("Job not found or no longer active", "danger");
    header("Location: browse-jobs.php");
    exit;
}

$job = $result->fetch_assoc();

// Check if user has already applied for this job
$check_application = "SELECT 1 FROM applications WHERE job_id = ? AND jobseeker_id = ?";
$stmt = $conn->prepare($check_application);
$stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
$stmt->execute();
$check_result = $stmt->get_result();

if ($check_result->num_rows > 0) {
    setMessage("You have already applied for this job", "warning");
    header("Location: job-details.php?id=$job_id");
    exit;
}

// Get job seeker information
$query = "SELECT * FROM jobseekers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$jobseeker_result = $stmt->get_result();
$jobseeker = $jobseeker_result->fetch_assoc();

// Process application form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cover_letter = sanitizeInput($_POST['cover_letter']);
    $use_existing_resume = isset($_POST['use_existing_resume']) ? true : false;
    
    // Validate inputs
    $errors = [];
    
    if (empty($cover_letter)) {
        $errors[] = "Cover letter is required";
    }
    
    // Handle resume upload or use existing
    $resume_file = null;
    
    if ($use_existing_resume) {
        if (empty($jobseeker['resume'])) {
            $errors[] = "You don't have an existing resume. Please upload one.";
        } else {
            $resume_file = $jobseeker['resume'];
        }
    } else {
        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Resume is required";
        } else {
            // Upload resume
            $upload_dir = 'assets/uploads/resumes/';
            $upload_result = uploadFile($_FILES['resume'], $upload_dir, ['pdf', 'doc', 'docx']);
            
            if (!$upload_result['status']) {
                $errors[] = $upload_result['message'];
            } else {
                $resume_file = $upload_result['filename'];
            }
        }
    }
    
    // If no validation errors, proceed with application
    if (empty($errors)) {
        // Create applications table if it doesn't exist
        $create_applications_table = "CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            jobseeker_id INT NOT NULL,
            resume VARCHAR(255),
            cover_letter TEXT,
            status ENUM('pending', 'reviewed', 'interviewed', 'offered', 'hired', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (jobseeker_id) REFERENCES jobseekers(id) ON DELETE CASCADE
        )";
        
        $conn->query($create_applications_table);
        
        // Insert application
        $query = "INSERT INTO applications (job_id, jobseeker_id, resume, cover_letter) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiss", $job_id, $_SESSION['user_id'], $resume_file, $cover_letter);
        
        if ($stmt->execute()) {
            // Update job applications count
            $update_query = "UPDATE jobs SET applications = applications + 1 WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            
            setMessage("Your application has been submitted successfully", "success");
            header("Location: jobseeker/applications.php");
            exit;
        } else {
            $errors[] = "Application submission failed. Please try again later.";
        }
    }
}

// Set page title
$page_title = "Apply for " . htmlspecialchars($job['title']);
$page_header = "Apply for Job";

// Include header
include_once 'includes/header.php';
?>

<div class="container mb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Apply for: <?php echo htmlspecialchars($job['title']); ?></h4>
                </div>
                <div class="card-body p-4">
                    <div class="job-summary mb-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-briefcase fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?job_id=" . $job_id); ?>" method="post" enctype="multipart/form-data" id="job-application-form">
                        <div class="mb-4">
                            <h5>Resume</h5>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Your resume should be in PDF, DOC, or DOCX format and should not exceed 5MB.
                            </div>
                            
                            <?php if(!empty($jobseeker['resume'])): ?>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="use_existing_resume" name="use_existing_resume" checked>
                                        <label class="form-check-label" for="use_existing_resume">
                                            Use my existing resume
                                        </label>
                                    </div>
                                    
                                    <div id="existing-resume" class="mt-2">
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-file-pdf fa-2x text-danger me-2"></i>
                                            <div>
                                                <p class="mb-0"><?php echo htmlspecialchars($jobseeker['resume']); ?></p>
                                                <small class="text-muted">Already uploaded to your profile</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div id="resume-upload" class="mb-3" <?php echo (!empty($jobseeker['resume'])) ? 'style="display: none;"' : ''; ?>>
                                <label for="resume" class="form-label">Upload Resume <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx">
                                <div class="form-text">We accept PDF, DOC, and DOCX files up to 5MB.</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5>Cover Letter <span class="text-danger">*</span></h5>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Your cover letter is your opportunity to explain why you're the best fit for this position. Make it clear, concise, and relevant.
                            </div>
                            
                            <div class="mb-3">
                                <label for="cover_letter" class="form-label">Cover Letter</label>
                                <textarea class="form-control" id="cover_letter" name="cover_letter" rows="8" required><?php echo isset($_POST['cover_letter']) ? htmlspecialchars($_POST['cover_letter']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I certify that all information provided in this application is true and complete to the best of my knowledge.
                            </label>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="job-details.php?id=<?php echo $job_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Job
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle resume upload section based on checkbox
    const useExistingResumeCheckbox = document.getElementById('use_existing_resume');
    const resumeUploadSection = document.getElementById('resume-upload');
    const existingResumeSection = document.getElementById('existing-resume');
    
    if (useExistingResumeCheckbox) {
        useExistingResumeCheckbox.addEventListener('change', function() {
            if (this.checked) {
                resumeUploadSection.style.display = 'none';
                existingResumeSection.style.display = 'block';
            } else {
                resumeUploadSection.style.display = 'block';
                existingResumeSection.style.display = 'none';
            }
        });
    }
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>
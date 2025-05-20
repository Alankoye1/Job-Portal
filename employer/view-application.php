<?php
// Set page title
$page_title = "Application Details";
$page_header = "Application Details";

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

// Get application details with job and applicant information
$query = "SELECT a.*, j.title as job_title, j.location as job_location, j.job_type, 
                 j.id as job_id, j.employer_id,
                 js.id as jobseeker_id, 
                 CONCAT(js.first_name, ' ', js.last_name) as applicant_name, 
                 js.email as applicant_email, js.phone as applicant_phone, 
                 js.location as applicant_location, js.headline as applicant_headline,
                 js.profile_pic, js.resume as jobseeker_resume, js.skills
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

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $new_status = sanitizeInput($_POST['status']);
    $valid_statuses = ['pending', 'reviewed', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected'];
    
    if (in_array($new_status, $valid_statuses)) {
        $update_query = "UPDATE applications SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $application_id);
        
        if ($stmt->execute()) {
            setMessage("Application status updated successfully.", "success");
            $application['status'] = $new_status; // Update the current view
        } else {
            setMessage("Failed to update application status.", "danger");
        }
    } else {
        setMessage("Invalid status.", "danger");
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mb-5">
    <!-- Breadcrumb navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="applications.php">Applications</a></li>
            <li class="breadcrumb-item active" aria-current="page">Application Details</li>
        </ol>
    </nav>
    
    <!-- Page header with action buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">Application Details</h1>
        <div>
            <!-- Action buttons -->
            <a href="applications.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Applications
            </a>
            <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" class="btn btn-outline-primary">
                <i class="fas fa-briefcase me-2"></i> View Job
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- Main application details -->
        <div class="col-lg-8 mb-4">
            <!-- Application overview -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Application Overview</h5>
                        <span class="badge <?php echo getStatusBadgeClass($application['status']); ?>">
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($application['job_title']); ?></h4>
                        <div class="text-muted mb-2">
                            <?php if($application['job_location']): ?>
                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($application['job_location']); ?>
                            <?php endif; ?>
                            
                            <?php if($application['job_type']): ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-briefcase me-1"></i> 
                                <?php echo htmlspecialchars(isset(getEmploymentTypes()[$application['job_type']]) ? getEmploymentTypes()[$application['job_type']] : $application['job_type']); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="text-muted">Applied: </span>
                            <strong><?php echo formatDate($application['created_at'], 'F j, Y \a\t g:i A'); ?></strong>
                            <span class="mx-2">•</span>
                            <span class="text-muted">Updated: </span>
                            <strong><?php echo formatDate($application['updated_at'], 'F j, Y \a\t g:i A'); ?></strong>
                        </div>
                    </div>
                    
                    <?php if($application['cover_letter']): ?>
                        <div class="mb-4">
                            <h5>Cover Letter</h5>
                            <div class="p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($application['resume']): ?>
                        <div>
                            <h5>Resume</h5>
                            <div class="d-flex align-items-center">
                                <i class="far fa-file-pdf fa-2x text-danger me-3"></i>
                                <div>
                                    <div><?php echo htmlspecialchars($application['resume']); ?></div>
                                    <div class="mt-2">
                                        <a href="/assets/uploads/resumes/<?php echo htmlspecialchars($application['resume']); ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                            <i class="fas fa-eye me-1"></i> View Resume
                                        </a>
                                        <a href="/assets/uploads/resumes/<?php echo htmlspecialchars($application['resume']); ?>" download class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif($application['jobseeker_resume']): ?>
                        <div>
                            <h5>Resume</h5>
                            <div class="d-flex align-items-center">
                                <i class="far fa-file-pdf fa-2x text-danger me-3"></i>
                                <div>
                                    <div><?php echo htmlspecialchars($application['jobseeker_resume']); ?> (Profile Resume)</div>
                                    <div class="mt-2">
                                        <a href="/assets/uploads/resumes/<?php echo htmlspecialchars($application['jobseeker_resume']); ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                            <i class="fas fa-eye me-1"></i> View Resume
                                        </a>
                                        <a href="/assets/uploads/resumes/<?php echo htmlspecialchars($application['jobseeker_resume']); ?>" download class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> No resume provided with this application.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Applicant details -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Applicant Information</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-4">
                        <div class="me-3">
                            <div class="avatar bg-light rounded-circle text-center me-2" style="width: 64px; height: 64px; line-height: 64px;">
                                <?php if($application['profile_pic']): ?>
                                    <img src="/assets/uploads/profile/<?php echo htmlspecialchars($application['profile_pic']); ?>" alt="Profile picture" class="rounded-circle img-fluid">
                                <?php else: ?>
                                    <i class="fas fa-user fa-2x text-primary" style="line-height: 64px;"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($application['applicant_name']); ?></h4>
                            <?php if($application['applicant_headline']): ?>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($application['applicant_headline']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Contact Information</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>">
                                        <?php echo htmlspecialchars($application['applicant_email']); ?>
                                    </a>
                                </li>
                                <?php if($application['applicant_phone']): ?>
                                    <li class="mb-2">
                                        <i class="fas fa-phone text-primary me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($application['applicant_phone']); ?>">
                                            <?php echo htmlspecialchars($application['applicant_phone']); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if($application['applicant_location']): ?>
                                    <li>
                                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                        <?php echo htmlspecialchars($application['applicant_location']); ?>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <?php if($application['skills']): ?>
                            <div class="col-md-6">
                                <h6>Skills</h6>
                                <div>
                                    <?php 
                                    $skills = explode(',', $application['skills']);
                                    foreach($skills as $skill): 
                                        $skill = trim($skill);
                                        if(!empty($skill)):
                                    ?>
                                        <span class="badge bg-light text-dark border me-1 mb-1"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-envelope me-2"></i> Contact Applicant
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Update Application Status -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Update Status</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $application_id); ?>" method="post">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Application Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" <?php echo $application['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="reviewed" <?php echo $application['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="shortlisted" <?php echo $application['status'] == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="interviewed" <?php echo $application['status'] == 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                <option value="offered" <?php echo $application['status'] == 'offered' ? 'selected' : ''; ?>>Offered</option>
                                <option value="hired" <?php echo $application['status'] == 'hired' ? 'selected' : ''; ?>>Hired</option>
                                <option value="rejected" <?php echo $application['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="applications.php?job_id=<?php echo $application['job_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-users me-2"></i> View All Applications for This Job
                        </a>
                        <a href="mailto:<?php echo htmlspecialchars($application['applicant_email']); ?>?subject=Regarding your application for <?php echo urlencode($application['job_title']); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-paper-plane me-2"></i> Email Candidate
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Application Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Application Timeline</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled timeline">
                        <li class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Application Received</h6>
                                <p class="text-muted mb-0"><?php echo formatDate($application['created_at'], 'F j, Y \a\t g:i A'); ?></p>
                            </div>
                        </li>
                        
                        <?php if($application['status'] != 'pending'): ?>
                            <li class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Application Reviewed</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($application['updated_at'], 'F j, Y'); ?></p>
                                </div>
                            </li>
                        <?php endif; ?>
                        
                        <?php if(in_array($application['status'], ['interviewed', 'offered', 'hired', 'rejected'])): ?>
                            <li class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Interview Conducted</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($application['updated_at'], 'F j, Y'); ?></p>
                                </div>
                            </li>
                        <?php endif; ?>
                        
                        <?php if(in_array($application['status'], ['offered', 'hired'])): ?>
                            <li class="timeline-item">
                                <div class="timeline-marker bg-warning"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Offer Extended</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($application['updated_at'], 'F j, Y'); ?></p>
                                </div>
                            </li>
                        <?php endif; ?>
                        
                        <?php if($application['status'] == 'hired'): ?>
                            <li class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Candidate Hired</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($application['updated_at'], 'F j, Y'); ?></p>
                                </div>
                            </li>
                        <?php endif; ?>
                        
                        <?php if($application['status'] == 'rejected'): ?>
                            <li class="timeline-item">
                                <div class="timeline-marker bg-danger"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Application Rejected</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($application['updated_at'], 'F j, Y'); ?></p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline styles */
.timeline {
    position: relative;
    padding-left: 12px;
}
.timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 20px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-marker {
    position: absolute;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    left: 0;
    top: 5px;
}
.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: 6px;
    height: 100%;
    width: 2px;
    background-color: #e9ecef;
}
</style>

<?php
// Helper function to get status badge class
function getStatusBadgeClass($status) {
    $status_badges = [
        'pending' => 'bg-warning text-dark',
        'reviewed' => 'bg-info text-dark',
        'shortlisted' => 'bg-info',
        'interviewed' => 'bg-primary',
        'offered' => 'bg-success',
        'hired' => 'bg-success',
        'rejected' => 'bg-danger'
    ];
    
    return isset($status_badges[$status]) ? $status_badges[$status] : 'bg-secondary';
}

// Include footer
include_once '../includes/footer.php';
?> 
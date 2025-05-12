<?php
// Set page title
$page_title = "Job Details";

// Include database connection
require_once 'config/db.php';
// Include functions
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if job ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setMessage("Job ID is required", "danger");
    header("Location: browse-jobs.php");
    exit;
}

$job_id = intval($_GET['id']);

// Get job details
$query = "SELECT j.*, e.company_name, e.logo, e.location as company_location, e.website
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

// Update view count
// Check if views column exists in the jobs table
$check_column = "SHOW COLUMNS FROM jobs LIKE 'views'";
$result_check = $conn->query($check_column);
if ($result_check->num_rows > 0) {
    $update_views = "UPDATE jobs SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($update_views);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
} else {
    // If views column doesn't exist, create it first
    $add_column = "ALTER TABLE jobs ADD COLUMN views INT DEFAULT 0";
    $conn->query($add_column);
    
    // Then update the view count
    $update_views = "UPDATE jobs SET views = 1 WHERE id = ?";
    $stmt = $conn->prepare($update_views);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
}

// Get similar jobs
$similar_jobs_query = "SELECT j.id, j.title, j.job_type, j.location, j.created_at, e.company_name
                      FROM jobs j
                      JOIN employers e ON j.employer_id = e.id
                      WHERE j.category = ? AND j.id != ? AND j.status = 'active' AND j.expires_at > NOW()
                      ORDER BY j.created_at DESC
                      LIMIT 3";
$stmt = $conn->prepare($similar_jobs_query);
$stmt->bind_param("si", $job['category'], $job_id);
$stmt->execute();
$similar_jobs_result = $stmt->get_result();

// Check if user has already applied for this job
$has_applied = false;
if (isLoggedIn() && isJobSeeker()) {
    $check_application = "SELECT 1 FROM applications WHERE job_id = ? AND jobseeker_id = ?";
    $stmt = $conn->prepare($check_application);
    $stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
    $stmt->execute();
    $check_result = $stmt->get_result();
    $has_applied = ($check_result->num_rows > 0);
}

// Set page title
$page_title = htmlspecialchars($job['title']) . " - " . htmlspecialchars($job['company_name']);
$page_header = htmlspecialchars($job['title']);

// Include header
include_once 'includes/header.php';
?>

<div class="container mb-5">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <!-- Job Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="company-logo me-3 bg-light rounded p-3" style="width: 100px; height: 100px;">
                            <?php if($job['logo']): ?>
                                <img src="/assets/uploads/logos/<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="img-fluid">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                    <i class="fas fa-building text-secondary fa-3x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h1 class="h2 mb-1"><?php echo htmlspecialchars($job['title']); ?></h1>
                            <p class="company-name mb-2">
                                <a href="#company-info" class="text-decoration-none">
                                    <?php echo htmlspecialchars($job['company_name']); ?>
                                </a>
                            </p>
                            
                            <div class="job-meta">
                                <?php if($job['job_type'] && array_key_exists($job['job_type'], getEmploymentTypes())): ?>
                                    <span class="badge bg-primary mb-1 me-1"><?php echo htmlspecialchars(getEmploymentTypes()[$job['job_type']]); ?></span>
                                <?php endif; ?>
                                
                                <?php if($job['location']): ?>
                                    <span class="badge bg-secondary mb-1 me-1">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($job['location']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if($job['category']): ?>
                                    <span class="badge bg-info text-dark mb-1 me-1">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars(getJobCategories()[$job['category']]); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if($job['featured']): ?>
                                    <span class="badge bg-warning text-dark mb-1">
                                        <i class="fas fa-star me-1"></i> Featured
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pt-3 border-top">
                        <div class="me-3 mb-2">
                            <p class="mb-0 text-muted">
                                <i class="far fa-calendar-alt me-1"></i> Posted <?php echo timeAgo($job['created_at']); ?>
                            </p>
                        </div>
                        
                        <div class="me-3 mb-2">
                            <p class="mb-0 text-muted">
                                <i class="far fa-eye me-1"></i> <?php echo isset($job['views']) ? $job['views'] : 0; ?> views
                            </p>
                        </div>
                        
                        <div class="me-3 mb-2">
                            <p class="mb-0 text-muted">
                                <i class="far fa-clock me-1"></i> Expires on <?php echo formatDate($job['expires_at']); ?>
                            </p>
                        </div>
                        
                        <div class="me-3 mb-2">
                            <p class="mb-0 text-muted">
                                <i class="far fa-file-alt me-1"></i> <?php echo isset($job['applications']) ? $job['applications'] : 0; ?> applications
                            </p>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2">
                        <?php if(isLoggedIn() && isJobSeeker()): ?>
                            <?php if($has_applied): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check me-2"></i> Applied
                                </button>
                            <?php else: ?>
                                <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-apply">
                                    <i class="fas fa-paper-plane me-2"></i> Apply Now
                                </a>
                            <?php endif; ?>
                        <?php elseif(!isLoggedIn()): ?>
                            <a href="auth/login.php?redirect=job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Login to Apply
                            </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Print
                        </button>
                        
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#shareModal">
                            <i class="fas fa-share-alt me-2"></i> Share
                        </button>
                        
                        <button class="btn btn-outline-secondary" id="saveJobBtn">
                            <i class="far fa-bookmark me-2"></i> Save
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Job Details -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Job Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <h5>Overview</h5>
                        <div class="job-description">
                            <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                        </div>
                    </div>
                    
                    <?php if(!empty($job['responsibilities'])): ?>
                        <div class="mb-4">
                            <h5>Responsibilities</h5>
                            <div class="job-responsibilities">
                                <?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($job['requirements'])): ?>
                        <div class="mb-4">
                            <h5>Requirements</h5>
                            <div class="job-requirements">
                                <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($job['benefits'])): ?>
                        <div class="mb-4">
                            <h5>Benefits</h5>
                            <div class="job-benefits">
                                <?php echo nl2br(htmlspecialchars($job['benefits'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex flex-wrap mt-4 pt-3 border-top">
                        <div class="me-4 mb-3">
                            <strong><i class="fas fa-money-bill-wave me-2"></i> Salary:</strong>
                            <?php if($job['salary_min'] && $job['salary_max']): ?>
                                $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?>
                                <?php if($job['salary_period']): ?>
                                    /<?php echo htmlspecialchars($job['salary_period']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Not specified
                            <?php endif; ?>
                        </div>
                        
                        <div class="me-4 mb-3">
                            <strong><i class="fas fa-briefcase me-2"></i> Job Type:</strong>
                            <?php echo ($job['job_type'] && array_key_exists($job['job_type'], getEmploymentTypes())) ? htmlspecialchars(getEmploymentTypes()[$job['job_type']]) : 'Not specified'; ?>
                        </div>
                        
                        <div class="me-4 mb-3">
                            <strong><i class="fas fa-map-marker-alt me-2"></i> Location:</strong>
                            <?php echo $job['location'] ? htmlspecialchars($job['location']) : 'Not specified'; ?>
                        </div>
                        
                        <div class="me-4 mb-3">
                            <strong><i class="fas fa-layer-group me-2"></i> Experience:</strong>
                            <?php echo (isset($job['experience_level']) && $job['experience_level'] && array_key_exists($job['experience_level'], getExperienceLevels())) ? htmlspecialchars(getExperienceLevels()[$job['experience_level']]) : 'Not specified'; ?>
                        </div>
                        
                        <div class="me-4 mb-3">
                            <strong><i class="fas fa-graduation-cap me-2"></i> Education:</strong>
                            <?php 
                                $education_levels = getEducationLevels();
                                echo (isset($job['education_level']) && $job['education_level'] && array_key_exists($job['education_level'], $education_levels)) ? 
                                    htmlspecialchars($education_levels[$job['education_level']]) : 'Not specified'; 
                            ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <?php if(isLoggedIn() && isJobSeeker()): ?>
                        <?php if($has_applied): ?>
                            <button class="btn btn-success w-100" disabled>
                                <i class="fas fa-check me-2"></i> You've Already Applied
                            </button>
                        <?php else: ?>
                            <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-apply w-100">
                                <i class="fas fa-paper-plane me-2"></i> Apply for this Position
                            </a>
                        <?php endif; ?>
                    <?php elseif(!isLoggedIn()): ?>
                        <a href="auth/login.php?redirect=job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i> Login to Apply
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Company Info -->
            <div class="card border-0 shadow-sm mb-4" id="company-info">
                <div class="card-header bg-white">
                    <h5 class="mb-0">About the Company</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row">
                        <div class="company-logo me-md-4 mb-3 mb-md-0 bg-light rounded p-3" style="width: 120px; height: 120px;">
                            <?php if($job['logo']): ?>
                                <img src="/assets/uploads/logos/<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="img-fluid">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                    <i class="fas fa-building text-secondary fa-3x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($job['company_name']); ?></h4>
                            
                            <div class="mb-3">
                                <?php if($job['company_location']): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($job['company_location']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if($job['website']): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-globe me-2 text-muted"></i>
                                        <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($job['website']); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-muted">No company description available.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <?php if(isLoggedIn() && isJobSeeker()): ?>
                        <?php if($has_applied): ?>
                            <div class="alert alert-success mb-3">
                                <i class="fas fa-check-circle me-2"></i> You have already applied for this job
                            </div>
                            <a href="jobseeker/applications.php" class="btn btn-outline-primary w-100 mb-3">
                                <i class="fas fa-list-alt me-2"></i> View Your Applications
                            </a>
                        <?php else: ?>
                            <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-paper-plane me-2"></i> Apply Now
                            </a>
                        <?php endif; ?>
                    <?php elseif(isLoggedIn() && isEmployer()): ?>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i> You are logged in as an employer
                        </div>
                        <a href="employer/post-job.php" class="btn btn-outline-primary w-100 mb-3">
                            <i class="fas fa-plus-circle me-2"></i> Post a Similar Job
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php?redirect=job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i> Login to Apply
                        </a>
                        <a href="auth/register.php?type=jobseeker&redirect=job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i> Register as Job Seeker
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-secondary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="fas fa-flag me-2"></i> Report this Job
                    </button>
                </div>
            </div>
            
            <!-- Job Overview -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Job Overview</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex mb-3">
                            <i class="fas fa-calendar-alt fa-fw text-primary me-3 mt-1"></i>
                            <div>
                                <strong>Posted Date</strong>
                                <p class="mb-0"><?php echo formatDate($job['created_at']); ?></p>
                            </div>
                        </li>
                        <li class="d-flex mb-3">
                            <i class="fas fa-hourglass-end fa-fw text-primary me-3 mt-1"></i>
                            <div>
                                <strong>Expiration Date</strong>
                                <p class="mb-0"><?php echo formatDate($job['expires_at']); ?></p>
                            </div>
                        </li>
                        <li class="d-flex mb-3">
                            <i class="fas fa-map-marker-alt fa-fw text-primary me-3 mt-1"></i>
                            <div>
                                <strong>Location</strong>
                                <p class="mb-0"><?php echo $job['location'] ? htmlspecialchars($job['location']) : 'Not specified'; ?></p>
                            </div>
                        </li>
                        <li class="d-flex mb-3">
                            <i class="fas fa-briefcase fa-fw text-primary me-3 mt-1"></i>
                            <div>
                                <strong>Job Type</strong>
                                <p class="mb-0">
                                <?php 
                                    $employment_types = getEmploymentTypes();
                                    if (!empty($job['job_type']) && isset($employment_types[$job['job_type']])) {
                                        echo htmlspecialchars($employment_types[$job['job_type']]);
                                    } else {
                                        echo 'Not specified';
                                    }
                                ?>
                                </p>
                            </div>
                        </li>
                        <li class="d-flex mb-3">
                            <i class="fas fa-money-bill-wave fa-fw text-primary me-3 mt-1"></i>
                            <div>
                                <strong>Salary</strong>
                                <p class="mb-0">
                                    <?php if($job['salary_min'] && $job['salary_max']): ?>
                                        $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?>
                                        <?php if($job['salary_period']): ?>
                                            /<?php echo htmlspecialchars($job['salary_period']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Not specified
                                    <?php endif; ?>
                                </p>
                            </div>
                        </li>
                        <li class="d-flex mb-3">
                            <i class="fas fa-layer-group fa-fw text-primary me-3 mt-1"></i>
                            <div>
                                <strong>Experience</strong>
                                <p class="mb-0"><?php echo (isset($job['experience_level']) && $job['experience_level'] && array_key_exists($job['experience_level'], getExperienceLevels())) ? htmlspecialchars(getExperienceLevels()[$job['experience_level']]) : 'Not specified'; ?></p>
                            </div>
                        </li>
                        <li class="d-flex">
                            <i class="fas fa-graduation-cap fa-fw text-primary me-3 mt-1"></i>
                            <div>
                                <strong>Education</strong>
                                <p class="mb-0"><?php echo (isset($job['education_level']) && $job['education_level'] && array_key_exists($job['education_level'], getEducationLevels())) ? htmlspecialchars(getEducationLevels()[$job['education_level']]) : 'Not specified'; ?></p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Similar Jobs -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Similar Jobs</h5>
                </div>
                <div class="card-body">
                    <?php if($similar_jobs_result && $similar_jobs_result->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while($similar_job = $similar_jobs_result->fetch_assoc()): ?>
                                <a href="job-details.php?id=<?php echo $similar_job['id']; ?>" class="list-group-item list-group-item-action border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($similar_job['title']); ?></h6>
                                            <p class="mb-1 small text-muted"><?php echo htmlspecialchars($similar_job['company_name']); ?></p>
                                            <div>
                                                <?php if($similar_job['job_type']): ?>
                                                    <span class="badge bg-primary me-1"><?php echo htmlspecialchars(getEmploymentTypes()[$similar_job['job_type']]); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if($similar_job['location']): ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($similar_job['location']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="text-muted small"><?php echo timeAgo($similar_job['created_at']); ?></span>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="browse-jobs.php?category=<?php echo urlencode($job['category']); ?>" class="btn btn-outline-primary btn-sm">
                                View More <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No similar jobs found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareModalLabel">Share This Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Share this job opportunity with your network:</p>
                
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode("http://{$_SERVER['HTTP_HOST']}/job-details.php?id={$job['id']}"); ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fab fa-facebook-f me-2"></i> Facebook
                    </a>
                    
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode("Check out this job: {$job['title']} at {$job['company_name']}"); ?>&url=<?php echo urlencode("http://{$_SERVER['HTTP_HOST']}/job-details.php?id={$job['id']}"); ?>" target="_blank" class="btn btn-outline-info">
                        <i class="fab fa-twitter me-2"></i> Twitter
                    </a>
                    
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode("http://{$_SERVER['HTTP_HOST']}/job-details.php?id={$job['id']}"); ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fab fa-linkedin-in me-2"></i> LinkedIn
                    </a>
                    
                    <a href="mailto:?subject=<?php echo urlencode("Job Opening: {$job['title']} at {$job['company_name']}"); ?>&body=<?php echo urlencode("I found this job that might interest you: {$job['title']} at {$job['company_name']}. Learn more at: http://{$_SERVER['HTTP_HOST']}/job-details.php?id={$job['id']}"); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-envelope me-2"></i> Email
                    </a>
                </div>
                
                <div class="input-group">
                    <input type="text" class="form-control" id="job-url" value="<?php echo "http://{$_SERVER['HTTP_HOST']}/job-details.php?id={$job['id']}"; ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" id="copy-url">
                        <i class="fas fa-copy me-2"></i> Copy
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalLabel">Report This Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="report-form">
                    <div class="mb-3">
                        <label for="report-reason" class="form-label">Reason</label>
                        <select class="form-select" id="report-reason" required>
                            <option value="">Select a reason</option>
                            <option value="spam">Spam or misleading</option>
                            <option value="fake">Fake job posting</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="scam">Potential scam</option>
                            <option value="duplicate">Duplicate posting</option>
                            <option value="other">Other reason</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="report-details" class="form-label">Details</label>
                        <textarea class="form-control" id="report-details" rows="3" placeholder="Please provide additional details about your report"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="report-email" class="form-label">Your Email (optional)</label>
                        <input type="email" class="form-control" id="report-email" placeholder="We'll contact you if we need more information">
                        <div class="form-text">We'll never share your email with anyone else.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="submit-report">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Copy URL functionality
    document.getElementById('copy-url').addEventListener('click', function() {
        const jobUrl = document.getElementById('job-url');
        jobUrl.select();
        document.execCommand('copy');
        
        // Change button text temporarily
        const copyBtn = this;
        const originalHtml = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check me-2"></i> Copied!';
        
        setTimeout(function() {
            copyBtn.innerHTML = originalHtml;
        }, 2000);
    });
    
    // Save job functionality
    document.getElementById('saveJobBtn').addEventListener('click', function() {
        const jobId = <?php echo $job_id; ?>;
        const button = this;
        
        // Get saved jobs from local storage
        let savedJobs = JSON.parse(localStorage.getItem('savedJobs')) || [];
        
        // Check if job is already saved
        const isJobSaved = savedJobs.includes(jobId);
        
        if (isJobSaved) {
            // Remove job from saved jobs
            savedJobs = savedJobs.filter(id => id !== jobId);
            button.innerHTML = '<i class="far fa-bookmark me-2"></i> Save';
        } else {
            // Add job to saved jobs
            savedJobs.push(jobId);
            button.innerHTML = '<i class="fas fa-bookmark me-2"></i> Saved';
        }
        
        // Save updated jobs to local storage
        localStorage.setItem('savedJobs', JSON.stringify(savedJobs));
    });
    
    // Check if job is saved on page load
    window.addEventListener('DOMContentLoaded', function() {
        const jobId = <?php echo $job_id; ?>;
        const savedJobs = JSON.parse(localStorage.getItem('savedJobs')) || [];
        
        if (savedJobs.includes(jobId)) {
            document.getElementById('saveJobBtn').innerHTML = '<i class="fas fa-bookmark me-2"></i> Saved';
        }
    });
    
    // Report form submission
    document.getElementById('submit-report').addEventListener('click', function() {
        const form = document.getElementById('report-form');
        const reason = document.getElementById('report-reason').value;
        
        if (!reason) {
            alert('Please select a reason for your report');
            return;
        }
        
        // In a real application, you would send this data to the server
        // For demo purposes, we'll just show a success message
        
        alert('Thank you for your report. We will review it shortly.');
        
        // Close the modal
        bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
    });
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>
<?php
// Set page title
$page_title = "Dashboard";
$page_header = "Dashboard";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is a job seeker
requireJobSeeker();

// Get job seeker information
$query = "SELECT * FROM jobseekers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$jobseeker = $result->fetch_assoc();

// Get application statistics
$applications_query = "SELECT 
                      COUNT(*) as total_applications,
                      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                      SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_applications,
                      SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed_applications,
                      SUM(CASE WHEN status = 'offered' THEN 1 ELSE 0 END) as offered_applications,
                      SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired_applications,
                      SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
                      FROM applications 
                      WHERE jobseeker_id = ?";
$stmt = $conn->prepare($applications_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get recent job applications
$recent_applications_query = "SELECT a.*, j.title as job_title, e.company_name
                            FROM applications a
                            JOIN jobs j ON a.job_id = j.id
                            JOIN employers e ON j.employer_id = e.id
                            WHERE a.jobseeker_id = ?
                            ORDER BY a.created_at DESC
                            LIMIT 5";
$stmt = $conn->prepare($recent_applications_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_applications_result = $stmt->get_result();

// Get recommended jobs based on profile
$recommended_jobs_query = "SELECT j.*, e.company_name, e.logo 
                          FROM jobs j
                          JOIN employers e ON j.employer_id = e.id
                          WHERE j.status = 'active' AND j.expires_at > NOW()";

// Add filters based on job seeker profile if available
$job_filters = [];
$job_params = [];
$job_types = "";

if (!empty($jobseeker['headline'])) {
    $keywords = explode(' ', $jobseeker['headline']);
    foreach ($keywords as $keyword) {
        if (strlen($keyword) > 3) {
            $job_filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $job_params[] = "%$keyword%";
            $job_params[] = "%$keyword%";
            $job_types .= "ss";
        }
    }
}

if (!empty($jobseeker['location'])) {
    $job_filters[] = "j.location LIKE ?";
    $job_params[] = "%{$jobseeker['location']}%";
    $job_types .= "s";
}

if (!empty($job_filters)) {
    $recommended_jobs_query .= " AND (" . implode(" OR ", $job_filters) . ")";
}

// Exclude jobs already applied for
$recommended_jobs_query .= " AND j.id NOT IN (
                            SELECT job_id FROM applications WHERE jobseeker_id = ?
                          )";
$job_params[] = $_SESSION['user_id'];
$job_types .= "i";

$recommended_jobs_query .= " ORDER BY j.created_at DESC LIMIT 4";

$stmt = $conn->prepare($recommended_jobs_query);
if (!empty($job_params)) {
    $stmt->bind_param($job_types, ...$job_params);
}
$stmt->execute();
$recommended_jobs_result = $stmt->get_result();

// Include header
include_once '../includes/header.php';
?>

<div class="container mb-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="mb-0">Welcome back, <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>!</h1>
            <p class="text-muted">Here's what's happening with your job applications</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="../browse-jobs.php" class="btn btn-primary">
                <i class="fas fa-search me-2"></i> Browse Jobs
            </a>
        </div>
    </div>
    
    <!-- Profile Completion Alert -->
    <?php
    $profile_fields = [
        'headline' => 'Professional headline',
        'summary' => 'Summary',
        'location' => 'Location',
        'phone' => 'Phone number',
        'resume' => 'Resume',
        'skills' => 'Skills',
        'experience' => 'Work experience',
        'education' => 'Education'
    ];
    
    $missing_fields = [];
    foreach ($profile_fields as $field => $label) {
        if (empty($jobseeker[$field])) {
            $missing_fields[] = $label;
        }
    }
    
    $completion_percentage = 100 - (count($missing_fields) / count($profile_fields) * 100);
    $completion_percentage = round($completion_percentage);
    
    if (!empty($missing_fields)):
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <div class="flex-grow-1">
                    <h5 class="mb-1">Complete Your Profile</h5>
                    <p class="text-muted mb-0">A complete profile increases your chances of getting noticed by employers</p>
                </div>
                <div class="ms-3">
                    <div class="position-relative d-inline-block">
                        <svg width="60" height="60" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="54" fill="none" stroke="#e9ecef" stroke-width="12" />
                            <circle cx="60" cy="60" r="54" fill="none" stroke="#0d6efd" stroke-width="12"
                                    stroke-dasharray="339.292" stroke-dashoffset="<?php echo 339.292 * (1 - $completion_percentage / 100); ?>" />
                            <text x="60" y="65" text-anchor="middle" dominant-baseline="middle" font-size="24" font-weight="bold" fill="#0d6efd"><?php echo $completion_percentage; ?>%</text>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $completion_percentage; ?>%"></div>
            </div>
            
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <p class="small mb-0">
                        Missing: <?php echo implode(', ', array_slice($missing_fields, 0, 3)); ?>
                        <?php if (count($missing_fields) > 3): ?>
                            and <?php echo count($missing_fields) - 3; ?> more
                        <?php endif; ?>
                    </p>
                </div>
                <div class="ms-3">
                    <a href="profile.php" class="btn btn-sm btn-primary">Complete Now</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-file-alt fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Total Applications</h6>
                            <h3 class="mb-0"><?php echo $stats['total_applications'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="applications.php" class="text-decoration-none small">
                        View all applications <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-eye fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Applications Viewed</h6>
                            <h3 class="mb-0"><?php echo ($stats['reviewed_applications'] + $stats['interviewed_applications'] + $stats['offered_applications'] + $stats['hired_applications'] + $stats['rejected_applications']) ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="applications.php?status=reviewed" class="text-decoration-none small">
                        View details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-user-tie fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Interviews</h6>
                            <h3 class="mb-0"><?php echo $stats['interviewed_applications'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="applications.php?status=interviewed" class="text-decoration-none small">
                        View interviews <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-check-circle fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Offers</h6>
                            <h3 class="mb-0"><?php echo ($stats['offered_applications'] + $stats['hired_applications']) ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="applications.php?status=offered" class="text-decoration-none small">
                        View offers <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Applications -->
        <div class="col-lg-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Applications</h5>
                        <a href="applications.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if($recent_applications_result && $recent_applications_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Job</th>
                                        <th scope="col">Company</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($application = $recent_applications_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($application['job_title']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($application['company_name']); ?></td>
                                            <td><?php echo formatDate($application['created_at']); ?></td>
                                            <td>
                                                <?php 
                                                $status_badges = [
                                                    'pending' => 'bg-warning text-dark',
                                                    'reviewed' => 'bg-info text-dark',
                                                    'interviewed' => 'bg-primary',
                                                    'offered' => 'bg-success',
                                                    'hired' => 'bg-success',
                                                    'rejected' => 'bg-danger'
                                                ];
                                                $badge_class = isset($status_badges[$application['status']]) ? $status_badges[$application['status']] : 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($application['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view-application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">You haven't applied to any jobs yet. <a href="../browse-jobs.php">Browse jobs</a> to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Profile Summary -->
        <div class="col-lg-5 mb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Profile Summary</h5>
                        <a href="profile.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-4">
                        <div class="profile-image me-3 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <?php if($jobseeker['profile_pic']): ?>
                                <img src="/assets/uploads/profile/<?php echo htmlspecialchars($jobseeker['profile_pic']); ?>" alt="<?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>" class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user text-secondary fa-2x"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?></h5>
                            <p class="mb-0 text-muted"><?php echo $jobseeker['headline'] ? htmlspecialchars($jobseeker['headline']) : 'Add your professional headline'; ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Contact Information</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-envelope text-primary me-2"></i>
                                            <?php echo htmlspecialchars($jobseeker['email']); ?>
                                        </li>
                                        <?php if($jobseeker['phone']): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-phone text-primary me-2"></i>
                                                <?php echo htmlspecialchars($jobseeker['phone']); ?>
                                            </li>
                                        <?php endif; ?>
                                        <?php if($jobseeker['location']): ?>
                                            <li>
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                <?php echo htmlspecialchars($jobseeker['location']); ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Resume</h6>
                                    <?php if($jobseeker['resume']): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-file-pdf text-danger me-2"></i>
                                            <div>
                                                <a href="/assets/uploads/resumes/<?php echo htmlspecialchars($jobseeker['resume']); ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 150px;">
                                                    <?php echo htmlspecialchars($jobseeker['resume']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No resume uploaded</p>
                                        <a href="profile.php#resume-section" class="btn btn-sm btn-outline-primary mt-2">Upload Resume</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if($jobseeker['skills']): ?>
                        <div class="mb-3">
                            <h6>Skills</h6>
                            <?php 
                            $skills = explode(',', $jobseeker['skills']);
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
                    <?php endif; ?>
                    
                    <?php if(empty($jobseeker['summary']) && empty($jobseeker['skills']) && empty($jobseeker['experience'])): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Your profile is incomplete. Add your skills, experience, and other details to improve your chances of getting hired.
                            <a href="profile.php" class="alert-link">Complete your profile now</a>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Application Status Chart & Recommended Jobs -->
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Application Status</h5>
                </div>
                <div class="card-body">
                    <?php if($stats['total_applications'] > 0): ?>
                        <canvas id="applicationStatusChart" height="250"></canvas>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Start applying to jobs to see your application statistics.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recommended Jobs for You</h5>
                        <a href="../browse-jobs.php" class="btn btn-sm btn-outline-primary">
                            Browse All Jobs <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if($recommended_jobs_result && $recommended_jobs_result->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while($job = $recommended_jobs_result->fetch_assoc()): ?>
                                <div class="list-group-item p-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-9 mb-2 mb-md-0">
                                            <div class="d-flex">
                                                <div class="company-logo me-3 bg-light rounded p-2" style="width: 50px; height: 50px;">
                                                    <?php if($job['logo']): ?>
                                                        <img src="/assets/uploads/logos/<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="img-fluid">
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                                            <i class="fas fa-building text-secondary"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">
                                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($job['title']); ?>
                                                        </a>
                                                    </h6>
                                                    <p class="mb-1 small text-muted">
                                                        <?php echo htmlspecialchars($job['company_name']); ?>
                                                        <?php if($job['location']): ?>
                                                            <span class="mx-1">•</span>
                                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <div>
                                                        <?php if($job['job_type']): ?>
                                                            <span class="badge bg-primary me-1"><?php 
                                                                // Convert underscores to hyphens if needed
                                                                $job_type = str_replace('_', '-', $job['job_type']);
                                                                echo htmlspecialchars(isset(getEmploymentTypes()[$job_type]) ? getEmploymentTypes()[$job_type] : $job['job_type']); 
                                                            ?></span>
                                                        <?php endif; ?>
                                                        <?php if($job['salary_min'] && $job['salary_max']): ?>
                                                            <span class="badge bg-success me-1">
                                                                $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="far fa-clock me-1"></i>
                                                            <?php echo timeAgo($job['created_at']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">View Job</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">Complete your profile to see job recommendations tailored to your skills and experience.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3 col-sm-6">
                            <a href="../browse-jobs.php" class="card text-decoration-none border-0 bg-light h-100">
                                <div class="card-body d-flex align-items-center">
                                    <i class="fas fa-search fa-2x text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0 text-dark">Find Jobs</h6>
                                        <p class="text-muted small mb-0">Browse open positions</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <a href="applications.php" class="card text-decoration-none border-0 bg-light h-100">
                                <div class="card-body d-flex align-items-center">
                                    <i class="fas fa-file-alt fa-2x text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0 text-dark">My Applications</h6>
                                        <p class="text-muted small mb-0">Track your job applications</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <a href="profile.php" class="card text-decoration-none border-0 bg-light h-100">
                                <div class="card-body d-flex align-items-center">
                                    <i class="fas fa-user-edit fa-2x text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0 text-dark">Update Profile</h6>
                                        <p class="text-muted small mb-0">Enhance your profile</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <a href="saved-jobs.php" class="card text-decoration-none border-0 bg-light h-100">
                                <div class="card-body d-flex align-items-center">
                                    <i class="fas fa-bookmark fa-2x text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0 text-dark">Saved Jobs</h6>
                                        <p class="text-muted small mb-0">View your saved listings</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Application Status Chart
    document.addEventListener('DOMContentLoaded', function() {
        <?php if($stats['total_applications'] > 0): ?>
        const applicationStatusChart = document.getElementById('applicationStatusChart');
        
        new Chart(applicationStatusChart, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Reviewed', 'Interviewed', 'Offered', 'Hired', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending_applications'] ?? 0; ?>, 
                        <?php echo $stats['reviewed_applications'] ?? 0; ?>, 
                        <?php echo $stats['interviewed_applications'] ?? 0; ?>, 
                        <?php echo $stats['offered_applications'] ?? 0; ?>, 
                        <?php echo $stats['hired_applications'] ?? 0; ?>, 
                        <?php echo $stats['rejected_applications'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#ffc107', // Warning (Pending)
                        '#0dcaf0', // Info (Reviewed)
                        '#0d6efd', // Primary (Interviewed)
                        '#20c997', // Teal (Offered)
                        '#198754', // Success (Hired)
                        '#dc3545'  // Danger (Rejected)
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>